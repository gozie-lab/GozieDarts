<?php
// Darts számláló – fiókok (név + jelszó, helyreállító kóddal). Felhasználónként egy JSON fájl a darts-data/users mappában.

const MAX_USERS   = 5000;
const MAX_TOKENS  = 6;               // egyszerre ennyi bejelentkezett eszköz
const MAX_HIST    = 300;
const LOCK_FAILS  = 8;               // ennyi hibás próbálkozás után ideiglenes zár
const LOCK_SECS   = 600;

function ac_key($name) { return mb_strtolower(trim($name)); }
function ac_clean_name($s) { return trim(preg_replace('/\s+/u', ' ', (string)$s)); }
function ac_name_ok($n) { return mb_strlen($n) >= 3 && mb_strlen($n) <= 20 && preg_match('/^[\p{L}\p{N} ._\-]+$/u', $n); }
function ac_exists($name) { global $ST; return $ST->has('user', st_user_key($name)); }

// felhasználó beolvasása + zárolása: [$fp, $user]
function ac_lock($name) {
    global $ST;
    [$h, $u] = $ST->lock('user', st_user_key($name));
    if (!$h) return [null, null];
    if (!is_array($u)) { $ST->release($h); return [null, null]; }
    return [$h, $u];
}
function ac_save($fp, $u) { save_room($fp, $u); }   // ugyanaz a kiírás-logika

// új fiók adatai (mentés nélkül): [$u, helyreállító kód]
// aktív felfüggesztés (lejárt után automatikusan megszűnik): [ts, by, until(0 = határozatlan), appeal?] vagy null
function ac_susp($u) {
    $b = $u['ban'] ?? null;
    if (empty($b)) return null;
    if (!is_array($b)) $b = ['ts' => 0, 'until' => 0];
    if (!empty($b['until']) && $b['until'] < time()) return null;
    return $b;
}
function ac_susp_fail($b) {
    $u = (int)($b['until'] ?? 0);
    fail('A fiókodat ideiglenesen felfüggesztették.', ['auth' => 1, 'banned' => 1, 'susp' => 1, 'until' => $u]);
}
function ac_create($name, $pw) {
    $code = ac_new_code();
    return [['name' => $name, 'created' => time(), 'hash' => password_hash($pw, PASSWORD_DEFAULT),
             'rec' => password_hash(ac_norm_code($code), PASSWORD_DEFAULT), 'tokens' => [], 'hist' => [], 'fails' => []], $code];
}
function ac_new_code() {
    $a = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // nincs 0/O/1/I/L
    $s = ''; for ($i = 0; $i < 12; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return implode('-', str_split($s, 4));
}
function ac_norm_code($c) { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$c)); }
function ac_new_token(&$u) {
    $t = bin2hex(random_bytes(24));
    $u['tokens'][] = ['h' => hash('sha256', $t), 'ts' => time()];
    if (count($u['tokens']) > MAX_TOKENS) $u['tokens'] = array_slice($u['tokens'], -MAX_TOKENS);
    return $t;
}
function ac_has_token($u, $t) {
    $h = hash('sha256', (string)$t);
    foreach ($u['tokens'] ?? [] as $k) if (hash_equals($k['h'], $h)) return true;
    return false;
}
// "név:token" formátumú azonosító a kérésekben: {name, token}
function ac_auth($in) {
    $n = ac_clean_name($in['uname'] ?? ''); $t = (string)($in['token'] ?? '');
    if ($n === '' || $t === '') return [null, null];
    [$fp, $u] = ac_lock($n);
    if (!$u) return [null, null];
    if (!ac_has_token($u, $t)) { release($fp); return [null, null]; }
    if ($sb = ac_susp($u)) { release($fp); ac_susp_fail($sb); }
    return [$fp, $u];
}
// csak ellenőrzés (zárolás nélkül tartó): a fiók neve, ha a token érvényes
function ac_verify($in) {
    [$fp, $u] = ac_auth($in);
    if (!$u) return null;
    release($fp);
    return $u['name'];
}
function ac_throttled(&$u) {
    $now = time();
    $u['fails'] = array_values(array_filter($u['fails'] ?? [], fn($t) => $now - $t < LOCK_SECS));
    return count($u['fails']) >= LOCK_FAILS;
}
function ac_hist_merge(&$u, $recs) {
    $by = [];
    foreach ($u['hist'] ?? [] as $r) if (isset($r['id'])) $by[$r['id']] = $r;
    foreach (array_slice($recs, 0, 100) as $r) {
        if (!is_array($r) || !isset($r['id']) || !is_string($r['id']) || strlen($r['id']) > 40) continue;
        if (strlen(json_encode($r)) > 6000) continue;
        $by[$r['id']] = $r;
    }
    $all = array_values($by);
    usort($all, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
    $u['hist'] = array_slice($all, 0, MAX_HIST);
}

function ac_action($act, $in) {
    switch ($act) {
    case 'reg': {
        $name = ac_clean_name($in['name'] ?? ''); $pw = (string)($in['pw'] ?? '');
        if (!ac_name_ok($name)) fail('A név 3–20 karakter lehet (betű, szám, szóköz, . _ -).');
        if (pf_bad($name) || mod_is_admin($name) || mb_stripos($name, DEL_PREFIX) === 0) fail('Ez a név nem használható.');
        if (mb_strlen($pw) < 6 || mb_strlen($pw) > 100) fail('A jelszó legalább 6 karakter legyen.');
        global $ST;
        if (!club_cfg()['reg']) fail('A regisztráció ebben a klubban zárt. Kérd az adminisztrátort, hogy hozza létre a fiókodat.');
        if ($ST->count('user') >= MAX_USERS) fail('Betelt a regisztrációk száma.');
        [$fp, $ex] = $ST->lock('user', st_user_key($name), true);
        if (!$fp) fail('Szerverhiba.');
        if ($ex !== null) { release($fp); fail('Ez a név már foglalt.'); }
        [$u, $code] = ac_create($name, $pw);
        $t = ac_new_token($u);
        ac_save($fp, $u);
        out(['ok' => 1, 'name' => $name, 'token' => $t, 'code' => $code, 'admin' => 0]);
    }
    case 'login': {
        $name = ac_clean_name($in['name'] ?? ''); $pw = (string)($in['pw'] ?? '');
        [$fp, $u] = ac_lock($name);
        if (!$u) fail('Hibás név vagy jelszó.');
        if (ac_throttled($u)) { release($fp); fail('Túl sok hibás próbálkozás, próbáld később (10 perc).'); }
        if (!password_verify($pw, $u['hash'])) { $u['fails'][] = time(); ac_save($fp, $u); fail('Hibás név vagy jelszó.'); }
        if ($sb = ac_susp($u)) { ac_save($fp, $u); ac_susp_fail($sb); }
        $u['fails'] = [];
        $t = ac_new_token($u);
        ac_save($fp, $u);
        out(['ok' => 1, 'name' => $u['name'], 'token' => $t, 'admin' => mod_is_admin($u['name']) ? 1 : 0]);
    }
    case 'recover': {
        $name = ac_clean_name($in['name'] ?? ''); $code = ac_norm_code($in['code'] ?? ''); $pw = (string)($in['pw'] ?? '');
        if (mb_strlen($pw) < 6 || mb_strlen($pw) > 100) fail('Az új jelszó legalább 6 karakter legyen.');
        [$fp, $u] = ac_lock($name);
        if (!$u) fail('Hibás név vagy helyreállító kód.');
        if (ac_throttled($u)) { release($fp); fail('Túl sok hibás próbálkozás, próbáld később (10 perc).'); }
        if (!password_verify($code, $u['rec'])) { $u['fails'][] = time(); ac_save($fp, $u); fail('Hibás név vagy helyreállító kód.'); }
        $u['fails'] = []; unset($u['pwreq']);
        $u['hash'] = password_hash($pw, PASSWORD_DEFAULT);
        $new = ac_new_code();
        $u['rec'] = password_hash(ac_norm_code($new), PASSWORD_DEFAULT);
        $u['tokens'] = [];                       // minden régi bejelentkezés érvénytelen
        $t = ac_new_token($u);
        ac_save($fp, $u);
        out(['ok' => 1, 'name' => $u['name'], 'token' => $t, 'code' => $new]);
    }
    case 'newcode': {
        [$fp, $u] = ac_auth($in);
        if (!$u) fail('Jelentkezz be újra.', ['auth' => 1]);
        if (ac_throttled($u)) { release($fp); fail('Túl sok hibás próbálkozás, próbáld később (10 perc).'); }
        if (!password_verify((string)($in['pw'] ?? ''), $u['hash'])) { $u['fails'][] = time(); ac_save($fp, $u); fail('Hibás jelszó.'); }
        $code = ac_new_code();
        $u['rec'] = password_hash(ac_norm_code($code), PASSWORD_DEFAULT);
        ac_save($fp, $u);
        out(['ok' => 1, 'code' => $code]);
    }
    case 'appeal': {   // fellebbezés felfüggesztés ellen (jelszóval igazolva, mert belépni nem tud)
        $name = ac_clean_name($in['name'] ?? ''); $pw = (string)($in['pw'] ?? '');
        $text = mb_substr(trim((string)($in['text'] ?? '')), 0, 500);
        if ($text === '') fail('Írd le, miért gondolod, hogy tévedés történt.');
        [$fp, $u] = ac_lock($name);
        if (!$u) fail('Hibás név vagy jelszó.');
        if (ac_throttled($u)) { release($fp); fail('Túl sok hibás próbálkozás, próbáld később (10 perc).'); }
        if (!password_verify($pw, $u['hash'])) { $u['fails'][] = time(); ac_save($fp, $u); fail('Hibás név vagy jelszó.'); }
        $b = ac_susp($u);
        if (!$b) { release($fp); fail('A fiókod nincs felfüggesztve.'); }
        if (!empty($b['appeal'])) { release($fp); fail('A fellebbezésedet már elküldted, az admin hamarosan megnézi.'); }
        $b['appeal'] = ['t' => $text, 'ts' => time()]; $u['ban'] = $b;
        $uname = $u['name'];
        ac_save($fp, $u);
        [$rp, $d] = rep_open();
        $d['n'] = ($d['n'] ?? 0) + 1;
        $d['l'][] = ['id' => $d['n'], 'ts' => time(), 'by' => $uname, 'who' => $uname, 'why' => 'appeal', 'note' => $text, 'ctx' => [], 'st' => 'open', 'kind' => 'appeal'];
        save_room($rp, $d);
        out(['ok' => 1]);
    }
    case 'pwreq': {   // elfelejtett jelszó: kérés az admintól (a válasz mindig ugyanaz, így nem derül ki, ki regisztrált)
        $name = ac_clean_name($in['name'] ?? '');
        if ($name !== '' && !mod_is_admin($name)) {
            [$fp, $u] = ac_lock($name);
            if ($u) {
                if (empty($u['pwreq']) || $u['pwreq'] < time() - 86400) { $u['pwreq'] = time(); ac_save($fp, $u); } else release($fp);
            }
        }
        out(['ok' => 1]);
    }
    case 'chpw': {
        [$fp, $u] = ac_auth($in);
        if (!$u) fail('Jelentkezz be újra.', ['auth' => 1]);
        $new = (string)($in['npw'] ?? '');
        if (mb_strlen($new) < 6 || mb_strlen($new) > 100) { release($fp); fail('Az új jelszó legalább 6 karakter legyen.'); }
        if (ac_throttled($u)) { release($fp); fail('Túl sok hibás próbálkozás, próbáld később (10 perc).'); }
        if (!password_verify((string)($in['pw'] ?? ''), $u['hash'])) { $u['fails'][] = time(); ac_save($fp, $u); fail('Hibás jelenlegi jelszó.'); }
        $u['hash'] = password_hash($new, PASSWORD_DEFAULT); unset($u['pwreq']);
        ac_save($fp, $u);
        out(['ok' => 1]);
    }
    case 'logout': {
        [$fp, $u] = ac_auth($in);
        if (!$u) out(['ok' => 1]);
        $h = hash('sha256', (string)$in['token']);
        $u['tokens'] = array_values(array_filter($u['tokens'], fn($k) => !hash_equals($k['h'], $h)));
        ac_save($fp, $u);
        out(['ok' => 1]);
    }
    case 'hsync': {
        [$fp, $u] = ac_auth($in);
        if (!$u) fail('Jelentkezz be újra.', ['auth' => 1]);
        $before = md5(json_encode([$u['hist'] ?? [], $u['pl'] ?? [], $u['pdel'] ?? [], $u['hdel'] ?? []]));
        $now = time();
        // törölt meccsek (tombstone), hogy más eszközről se jöjjenek vissza
        $hd = $u['hdel'] ?? [];
        foreach (array_slice(is_array($in['del'] ?? null) ? $in['del'] : [], 0, 300) as $id) {
            if (is_string($id) && $id !== '' && strlen($id) <= 40) $hd[$id] = $hd[$id] ?? $now;
        }
        if (count($hd) > 1000) { arsort($hd); $hd = array_slice($hd, 0, 1000, true); }
        $u['hdel'] = $hd;
        $u['hist'] = array_values(array_filter($u['hist'] ?? [], fn($r) => !isset($hd[$r['id'] ?? ''])));
        $recs = $in['recs'] ?? [];
        if (is_array($recs) && count($recs)) {
            $recs = array_values(array_filter($recs, fn($r) => is_array($r) && !isset($hd[$r['id'] ?? ''])));
            ac_hist_merge($u, $recs);
        }
        // mentett játékosok: név -> hozzáadás ideje; törlések időbélyeggel (a későbbi művelet nyer)
        $pl = $u['pl'] ?? []; $pd = $u['pdel'] ?? [];
        $nk = fn($n) => mb_strtolower(trim((string)$n));
        foreach (array_slice(is_array($in['pdel'] ?? null) ? $in['pdel'] : [], 0, 300, true) as $k => $ts) {
            $k = $nk($k); $ts = (int)$ts;
            if ($k === '' || mb_strlen($k) > 20) continue;
            if (isset($pl[$k]) && $pl[$k]['ts'] > $ts) continue;
            unset($pl[$k]); $pd[$k] = max($pd[$k] ?? 0, $ts);
        }
        foreach (array_slice(is_array($in['pl'] ?? null) ? $in['pl'] : [], 0, 200) as $q) {
            if (!is_array($q)) continue;
            $n = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($q['n'] ?? ''))), 0, 20); $k = $nk($n); $ts = (int)($q['ts'] ?? 1);
            if ($k === '' || pf_bad($n)) continue;
            if (($pd[$k] ?? -1) >= $ts) continue;
            if (!isset($pl[$k]) || $pl[$k]['ts'] < $ts) $pl[$k] = ['n' => $n, 'ts' => $ts];
            if (isset($pd[$k]) && $pd[$k] < $ts) unset($pd[$k]);
        }
        if (count($pl) > 200) { uasort($pl, fn($a, $b) => $b['ts'] <=> $a['ts']); $pl = array_slice($pl, 0, 200, true); }
        if (count($pd) > 300) { arsort($pd); $pd = array_slice($pd, 0, 300, true); }
        $u['pl'] = $pl; $u['pdel'] = $pd;
        $after = md5(json_encode([$u['hist'], $u['pl'], $u['pdel'], $u['hdel']]));
        if ($after !== $before) ac_save($fp, $u); else release($fp);
        out(['ok' => 1, 'name' => $u['name'], 'hist' => $u['hist'], 'pl' => array_values($pl), 'pdel' => (object)$pd, 'hdel' => (object)$hd]);
    }
    case 'me': {
        $n = ac_verify($in);
        if ($n === null) fail('Jelentkezz be újra.', ['auth' => 1]);
        out(['ok' => 1, 'name' => $n, 'admin' => mod_is_admin($n) ? 1 : 0]);
    }
    }
}
