<?php
// Gozie Darts – moderáció: szitokszűrő, jelentések, adminisztrátor, felfüggesztés, fiók törlése, jelenlét (online létszám).
// Admin: a darts-config.php 'admins' => ['Név', ...] listája. FONTOS: előbb regisztráld a nevet az appban, csak utána add a listához
// (a listán lévő nevet új regisztrációval nem lehet lefoglalni).

const REP_MAX_DAY = 5, REP_KEEP = 60 * 86400, PRES_TTL = 120, DEL_PREFIX = 'Törölt játékos';
const REP_WHY = ['name' => 'Sértő név', 'chat' => 'Sértő üzenet', 'cheat' => 'Csalás', 'harass' => 'Zaklatás', 'other' => 'Egyéb'];

// alapadminok: darts-config.php ['admins']; további adminokat az alapadmin jelölhet ki az admin felületen (doc 'admins')
function mod_primary() {
    global $CFG; $a = $CFG['admins'] ?? [];
    return is_array($a) ? array_map('ac_key', $a) : [];
}
function mod_admins() {
    global $ST;
    $d = $ST ? $ST->get('doc', 'admins') : null;
    return array_values(array_unique(array_merge(mod_primary(), is_array($d['l'] ?? null) ? array_map('ac_key', $d['l']) : [])));
}
function mod_is_primary($name) { return in_array(ac_key($name), mod_primary(), true); }
function mod_is_admin($name) { return in_array(ac_key($name), mod_admins(), true); }

// ---------- szitokszűrő ----------
function pf_fold($s) {
    $s = mb_strtolower((string)$s);
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u', 'ä' => 'a', 'ß' => 'ss',
                    '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
    return preg_replace('/(.)\1+/u', '$1', $s);          // fuuuck -> fuck
}
function pf_lists() {
    static $L = null; if ($L) return $L;
    $p = ['kurva', 'fasz', 'geci', 'picsa', 'basz', 'kibasz', 'megbasz', 'bazdmeg', 'bazmeg', 'bazzeg', 'buzi', 'kocsog', 'szarhazi', 'szarjanko', 'szaros', 'csicska',
          'rohadek', 'szopd', 'szopj', 'lofasz', 'fuck', 'motherfuck', 'shit', 'bitch', 'cunt', 'asshole', 'bastard', 'whore', 'slut', 'wank', 'faggot', 'retard', 'nigger', 'nigga',
          'pussy', 'dickhead', 'scheis', 'arschloch', 'wichs', 'fotze', 'hurensohn', 'schlampe', 'fick', 'missgeburt', 'spast', 'vollidiot', 'kanake', 'neger'];
    $e = ['dick', 'cock', 'prick', 'twat', 'ass', 'hure', 'huren', 'nazi', 'kike', 'chink', 'spic', 'tranny', 'pina'];
    $ok = ['faszen'];     // ártalmas szavak, amik egyező kezdetűek (pl. faszén)
    $L = ['p' => array_map('pf_fold', $p), 'e' => array_values(array_diff(array_map('pf_fold', $e), ['pina'])), 'ok' => array_map('pf_fold', $ok)];
    return $L;
}
function pf_hit($f) {
    $L = pf_lists();
    if ($f === '') return false;
    foreach ($L['ok'] as $o) if (strpos($f, $o) === 0) return false;
    foreach ($L['e'] as $w) if ($f === $w) return true;
    foreach ($L['p'] as $w) if (strpos($f, $w) === 0) return true;
    return false;
}
// [maszkolt szöveg, találat volt-e]
function pf_clean($text) {
    $text = (string)$text;
    if (!preg_match_all('/[\p{L}\p{N}@$]+/u', $text, $m, PREG_OFFSET_CAPTURE)) return [$text, false];
    $tk = [];
    foreach ($m[0] as [$t, $o]) $tk[] = ['t' => $t, 's' => $o, 'e' => $o + strlen($t)];
    $n = count($tk); $ranges = [];
    for ($i = 0; $i < $n; $i++) {
        // egybetűs, elválasztókkal szétszedett szó (f.a.s.z)
        $j = $i; $comb = $tk[$i]['t'];
        if (mb_strlen($tk[$i]['t']) === 1) {
            while ($j + 1 < $n && mb_strlen($tk[$j + 1]['t']) === 1 && $tk[$j + 1]['s'] - $tk[$j]['e'] <= 2) { $j++; $comb .= $tk[$j]['t']; }
            if ($j > $i && pf_hit(pf_fold($comb))) { $ranges[] = [$tk[$i]['s'], $tk[$j]['e']]; $i = $j; continue; }
            $j = $i;
        }
        if (pf_hit(pf_fold($tk[$i]['t']))) $ranges[] = [$tk[$i]['s'], $tk[$i]['e']];
    }
    if (!$ranges) return [$text, false];
    $out = ''; $pos = 0;
    foreach ($ranges as [$s, $e]) { $out .= substr($text, $pos, $s - $pos) . str_repeat('*', mb_strlen(substr($text, $s, $e - $s))); $pos = $e; }
    return [$out . substr($text, $pos), true];
}
function pf_bad($text) { return pf_clean($text)[1]; }

// ---------- jelenlét ----------
function pres_action($in) {
    global $ST;
    $id = preg_replace('/[^a-zA-Z0-9]/', '', (string)($in['id'] ?? ''));
    [$fp, $d] = $ST->lock('doc', 'presence', true); if (!is_array($d)) $d = [];
    $now = time();
    foreach ($d as $k => $t) if ($now - $t > PRES_TTL) unset($d[$k]);
    if ($id !== '' && strlen($id) <= 40 && (isset($d[$id]) || count($d) < 5000)) $d[$id] = $now;
    $c = count($d);
    save_room($fp, $d);
    out(['ok' => 1, 'n' => $c]);
}

// ---------- jelentések, admin ----------
function rep_open() {
    global $ST;
    [$fp, $d] = $ST->lock('doc', 'reports', true);
    if (!is_array($d) || !isset($d['l'])) $d = ['l' => [], 'n' => 0];
    $now = time();
    $d['l'] = array_values(array_filter($d['l'], fn($r) => $now - $r['ts'] < REP_KEEP));
    return [$fp, $d];
}
function mod_require_admin($u) {
    if (!mod_is_admin($u['name'])) fail('Ehhez nincs jogosultságod.');
}
function mod_user_view($name) {
    $u = fr_read_user($name);
    return $u ? ['name' => $u['name'], 'created' => $u['created'] ?? 0, 'ban' => (bool)ac_susp($u), 'adm' => mod_is_admin($u['name'])] : null;
}

// a fiók és a hozzá kapcsolódó adatok teljes törlése
function ac_purge($name) {
    global $ST;
    $key = ac_key($name); $own = st_user_key($name);
    $pseudo = DEL_PREFIX . ' #' . substr(sha1($key . 'x'), 0, 4);
    // 1) kapcsolatok és tiltások a többi fiókban
    foreach ($ST->keys('user') as $uk) {
        if ($uk === $own) continue;
        [$fp, $u] = $ST->lock('user', $uk); if (!$fp) continue;
        if (is_array($u) && (isset($u['fr'][$key]) || isset($u['bl'][$key]))) { unset($u['fr'][$key], $u['bl'][$key]); save_room($fp, $u); } else release($fp);
    }
    // 2) üzenetek
    [$fp, $d] = ms_open();
    foreach (array_keys($d['c']) as $k) if (in_array($key, explode('|', $k), true)) unset($d['c'][$k]);
    save_room($fp, $d);
    // 3) kihívások
    [$fp, $d] = ch_open();
    $d['l'] = array_values(array_filter($d['l'], fn($c) => ac_key($c['from']) !== $key && ac_key($c['to']) !== $key));
    save_room($fp, $d);
    // 4) ranglista: a meccsek megmaradnak, de a név álnévre cserélődik (az ellenfelek pontszáma így nem változik)
    [$fp, $d] = rk_open();
    foreach ($d['m'] as $k => $m) {
        if (ac_key($m['a']) === $key) $d['m'][$k]['a'] = $pseudo;
        if (ac_key($m['b']) === $key) $d['m'][$k]['b'] = $pseudo;
    }
    save_room($fp, $d);
    // 5) a fiók
    $ST->del('user', $own);
}

// az adminnak értesítést igénylő tételek: nyitott jelentések + fellebbezések + jelszókérések
function mod_pending() {
    global $ST;
    $d = $ST->get('doc', 'reports'); $n = 0;
    foreach (is_array($d['l'] ?? null) ? $d['l'] : [] as $r) if (($r['st'] ?? '') === 'open') $n++;
    foreach ($ST->all('user') as $x) if (!empty($x['pwreq'])) $n++;
    return $n;
}
function mod_gen_pw() { $a = 'abcdefghjkmnpqrstuvwxyz23456789'; $s = ''; for ($i = 0; $i < 8; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; return $s; }

function mod_action($act, $in) {
    global $ST;
    [$afp, $u] = ac_auth($in);
    if (!$u) fail('Jelentkezz be.', ['auth' => 1]);
    release($afp);
    $me = $u['name'];

    if ($act === 'report') {
        $who = ac_clean_name($in['who'] ?? '');
        $o = $who !== '' ? fr_read_user($who) : null;
        if (!$o) fail('Nincs ilyen regisztrált játékos.');
        if (ac_key($o['name']) === ac_key($me)) fail('Magadat nem jelentheted.');
        $why = (string)($in['why'] ?? 'other'); if (!isset(REP_WHY[$why])) $why = 'other';
        $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['note'] ?? ''))), 0, 200);
        $ctx = [];
        $mts = is_array($in['mts'] ?? null) ? array_slice(array_map('intval', $in['mts']), 0, 5) : [];
        if ($mts && (($u['fr'][ac_key($o['name'])]['st'] ?? '') === 'friend')) {     // a jelentő által megjelölt üzenetek (csak a jelentett játékos üzenetei)
            $d = $ST->get('doc', 'msgs') ?: ['c' => []];
            $c = $d['c'][ms_key($me, $o['name'])] ?? null;
            if ($c) { $s = ms_side($me, $o['name']); $other = 1 - $s;
                foreach ($c['m'] as $m) if ($m['f'] === $other && in_array((int)$m['ts'], $mts, true)) $ctx[] = ['t' => $m['t'], 'ts' => (int)$m['ts']]; }
        }
        [$fp, $d] = rep_open();
        $now = time(); $cnt = 0;
        foreach ($d['l'] as $r) {
            if (ac_key($r['by']) === ac_key($me) && $now - $r['ts'] < 86400) $cnt++;
            if (ac_key($r['by']) === ac_key($me) && ac_key($r['who']) === ac_key($o['name']) && $r['st'] === 'open') { release($fp); out(['ok' => 1, 'dup' => 1]); }
        }
        if ($cnt >= REP_MAX_DAY) { release($fp); fail('Ma már elérted a jelentések maximális számát.'); }
        $d['n'] = ($d['n'] ?? 0) + 1;
        $d['l'][] = ['id' => $d['n'], 'ts' => $now, 'by' => $me, 'who' => $o['name'], 'why' => $why, 'note' => $note, 'ctx' => $ctx, 'st' => 'open'];
        save_room($fp, $d);
        out(['ok' => 1]);
    }

    if ($act === 'ac_delete') {   // saját fiók törlése
        [$fp, $u] = ac_lock($me);
        if (!$u) fail('Jelentkezz be újra.', ['auth' => 1]);
        if (ac_throttled($u)) { release($fp); fail('Túl sok hibás próbálkozás, próbáld később (10 perc).'); }
        if (!password_verify((string)($in['pw'] ?? ''), $u['hash'])) { $u['fails'][] = time(); ac_save($fp, $u); fail('Hibás jelszó.'); }
        release($fp);
        ac_purge($me);
        out(['ok' => 1]);
    }

    // innentől csak admin
    mod_require_admin($u);
    if ($act === 'adm_list') {
        [$fp, $d] = rep_open(); save_room($fp, $d);
        $L = array_reverse($d['l']);
        usort($L, fn($a, $b) => [$a['st'] !== 'open'] <=> [$b['st'] !== 'open']);
        $banned = [];
        foreach ($ST->all('user') as $x) { if ($b = ac_susp($x)) $banned[] = ['name' => $x['name'], 'until' => (int)($b['until'] ?? 0), 'appeal' => !empty($b['appeal'])]; }
        out(['ok' => 1, 'reports' => array_slice($L, 0, 100), 'bans' => $banned, 'users' => $ST->count('user'), 'reqs' => count(array_filter($ST->all('user'), fn($x) => !empty($x['pwreq'])))]);
    }
    if ($act === 'adm_upd_check' || $act === 'adm_upd_apply') { require_once __DIR__ . '/updater.php'; up_action($act, $in, $me); }
    if ($act === 'adm_club_get') out(array_merge(['ok' => 1], club_cfg()));
    if ($act === 'adm_club_set') {
        $c = club_clean($in);
        if ($c['color'] !== '') {
            $m = sscanf($c['color'], '#%02x%02x%02x');
            if ((0.2126 * $m[0] + 0.7152 * $m[1] + 0.0722 * $m[2]) / 255 > 0.62) fail('Ez a szín túl világos (a gombokon fehér a felirat). Válassz sötétebbet.');
        }
        if ($c['club'] !== '' && pf_bad($c['club'])) fail('A klub neve nem használható.');
        if (($in['logo'] ?? '') !== '' && $c['logo'] === '') fail('A logó https címe vagy a feltöltött kép fájlneve (png, jpg, svg, webp) érvényes.');
        if (!array_key_exists('logo', $in)) $c['logo'] = club_cfg()['logo'];
        if (!array_key_exists('reg', $in)) $c['reg'] = club_cfg()['reg'];
        club_save($c);
        out(array_merge(['ok' => 1], club_cfg()));
    }
    if ($act === 'adm_users') {
        $q = mb_strtolower(trim((string)($in['q'] ?? '')));
        $L = [];
        foreach ($ST->all('user') as $x) {
            if ($q !== '' && mb_strpos(mb_strtolower($x['name']), $q) === false) continue;
            $L[] = ['name' => $x['name'], 'created' => $x['created'] ?? 0, 'ban' => (bool)ac_susp($x), 'until' => (int)(ac_susp($x)['until'] ?? 0), 'adm' => mod_is_admin($x['name']), 'pri' => mod_is_primary($x['name']), 'req' => (int)($x['pwreq'] ?? 0)];
        }
        usort($L, fn($a, $b) => [$b['req'] > 0, $b['created']] <=> [$a['req'] > 0, $a['created']]);
        $reqs = array_values(array_filter($L, fn($u) => $u['req'] > 0));
        usort($reqs, fn($a, $b) => $a['req'] <=> $b['req']);
        $off = max(0, (int)($in['off'] ?? 0));
        out(['ok' => 1, 'total' => count($L), 'users' => array_slice($L, $off, 50), 'me_pri' => mod_is_primary($me), 'reqs' => array_slice($reqs, 0, 50)]);
    }
    if ($act === 'adm_user_add') {
        $name = ac_clean_name($in['name'] ?? ''); $pw = trim((string)($in['pw'] ?? ''));
        if (!ac_name_ok($name)) fail('A név 3–20 karakter lehet (betű, szám, szóköz, . _ -).');
        if (pf_bad($name) || mod_is_admin($name) || mb_stripos($name, DEL_PREFIX) === 0) fail('Ez a név nem használható.');
        if ($pw === '') $pw = mod_gen_pw();
        if (mb_strlen($pw) < 6 || mb_strlen($pw) > 100) fail('A jelszó legalább 6 karakter legyen.');
        if ($ST->count('user') >= MAX_USERS) fail('Betelt a regisztrációk száma.');
        [$fp, $ex] = $ST->lock('user', st_user_key($name), true);
        if (!$fp) fail('Szerverhiba.');
        if ($ex !== null) { release($fp); fail('Ez a név már foglalt.'); }
        [$x, $code] = ac_create($name, $pw);
        ac_save($fp, $x);
        out(['ok' => 1, 'name' => $name, 'pw' => $pw, 'code' => $code]);
    }
    if ($act === 'adm_pw') {
        $name = ac_clean_name($in['name'] ?? ''); $pw = trim((string)($in['pw'] ?? ''));
        $v = $name !== '' ? mod_user_view($name) : null;
        if (!$v) fail('Nincs ilyen regisztrált játékos.');
        if ($v['adm'] && ac_key($v['name']) !== ac_key($me)) fail('Másik adminisztrátor jelszavát nem lehet megváltoztatni.');
        if ($pw === '') $pw = mod_gen_pw();
        if (mb_strlen($pw) < 6 || mb_strlen($pw) > 100) fail('A jelszó legalább 6 karakter legyen.');
        [$fp, $x] = ac_lock($v['name']);
        if (!$x) fail('Nincs ilyen regisztrált játékos.');
        $x['hash'] = password_hash($pw, PASSWORD_DEFAULT); unset($x['pwreq']);
        $x['tokens'] = []; $x['fails'] = [];   // minden eszközről kilépteti
        ac_save($fp, $x);
        out(['ok' => 1, 'name' => $v['name'], 'pw' => $pw]);
    }
    if ($act === 'adm_admin') {
        if (!mod_is_primary($me)) fail('Adminisztrátort csak az alapadmin jelölhet ki.');
        $name = ac_clean_name($in['name'] ?? ''); $on = !empty($in['on']);
        $v = $name !== '' ? mod_user_view($name) : null;
        if (!$v) fail('Nincs ilyen regisztrált játékos.');
        if (mod_is_primary($v['name'])) fail('Az alapadmin jogát itt nem lehet módosítani.');
        if ($on && $v['ban']) fail('Felfüggesztett játékos nem lehet admin.');
        [$fp, $d] = $ST->lock('doc', 'admins', true);
        $L = is_array($d['l'] ?? null) ? $d['l'] : [];
        $k = ac_key($v['name']);
        $L = array_values(array_filter($L, fn($x) => ac_key($x) !== $k));
        if ($on) $L[] = $v['name'];
        save_room($fp, ['l' => array_slice($L, 0, 20)]);
        out(['ok' => 1]);
    }
    if ($act === 'adm_logo') {
        $raw = base64_decode((string)($in['data'] ?? ''), true);
        [$fn, $err] = logo_save($raw === false ? '' : $raw);
        if (!$fn) fail($err);
        $c = club_cfg(); $c['logo'] = $fn; club_save($c);
        out(array_merge(['ok' => 1], club_cfg()));
    }
    if ($act === 'adm_logo_del') {
        logo_remove(); $c = club_cfg(); $c['logo'] = ''; club_save($c);
        out(array_merge(['ok' => 1], club_cfg()));
    }
    if ($act === 'adm_act') {
        $op = (string)($in['op'] ?? ''); $name = ac_clean_name($in['name'] ?? ''); $rid = (int)($in['rid'] ?? 0);
        if ($op === 'dismiss') {
            [$fp, $d] = rep_open();
            foreach ($d['l'] as &$r) if ($r['id'] === $rid) $r['st'] = 'dismissed';
            save_room($fp, $d); out(['ok' => 1]);
        }
        if ($op === 'rep_del' || $op === 'rep_clear') {
            [$fp, $d] = rep_open();
            $d['l'] = array_values(array_filter($d['l'], fn($r) => $r['st'] === 'open' || ($op === 'rep_del' && $r['id'] !== $rid)));
            save_room($fp, $d); out(['ok' => 1]);
        }
        $v = $name !== '' ? mod_user_view($name) : null;
        if (!$v) fail('Nincs ilyen regisztrált játékos.');
        if ($v['adm']) fail('Adminisztrátorra ez nem alkalmazható.');
        if ($op === 'ban' || $op === 'unban') {
            [$fp, $x] = ac_lock($v['name']);
            $days = max(0, min(3650, (int)($in['days'] ?? 0)));
            if ($op === 'ban') { $x['ban'] = ['ts' => time(), 'by' => $me, 'until' => $days ? time() + $days * 86400 : 0]; } else unset($x['ban']);
            ac_save($fp, $x);
            [$fp, $d] = rep_open();
            foreach ($d['l'] as &$r) if (ac_key($r['who']) === ac_key($v['name']) && $r['st'] === 'open' && (($r['kind'] ?? '') === 'appeal') === ($op === 'unban')) $r['st'] = 'actioned';
            save_room($fp, $d);
            out(['ok' => 1]);
        }
        if ($op === 'delete') {
            ac_purge($v['name']);
            [$fp, $d] = rep_open(); foreach ($d['l'] as &$r) if (ac_key($r['who']) === ac_key($v['name']) && $r['st'] === 'open') $r['st'] = 'actioned'; save_room($fp, $d);
            out(['ok' => 1]);
        }
        fail('Ismeretlen művelet.');
    }
    fail('Ismeretlen művelet.');
}
