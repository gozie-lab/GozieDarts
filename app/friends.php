<?php
// Darts számláló – barátok: kérések, jelölés (kedvenc), törlés, tiltás, üzenetek.
// A kapcsolatok a felhasználói fájlokban vannak: fr = [kulcs => [n, st(friend|out|in), fav]], bl = [kulcs => [n]].

const FR_MAX = 100, MSG_MAX = 100, MSG_LEN = 300, ONLINE_SECS = 90;

function fr_pair($a, $b, $fn) {
    $ka = ac_key($a); $kb = ac_key($b);
    if ($ka === $kb) fail('Magaddal nem teheted meg.');
    if (strcmp($ka, $kb) < 0) { [$fa, $ua] = ac_lock($a); [$fb, $ub] = ac_lock($b); }
    else { [$fb, $ub] = ac_lock($b); [$fa, $ua] = ac_lock($a); }
    if (!$ua || !$ub) { if ($fa) release($fa); if ($fb) release($fb); fail('Nincs ilyen regisztrált játékos.'); }
    $r = $fn($ua, $ub);
    if (is_string($r)) { release($fa); release($fb); fail($r); }
    save_room($fa, $ua); save_room($fb, $ub);
    return $r;
}
function ms_open() {
    global $ST;
    [$fp, $d] = $ST->lock('doc', 'msgs', true);
    if (!is_array($d) || !isset($d['c'])) $d = ['c' => []];
    return [$fp, $d];
}
function ms_key($a, $b) { $x = [ac_key($a), ac_key($b)]; sort($x); return implode('|', $x); }
function ms_side($me, $other) { return strcmp(ac_key($me), ac_key($other)) < 0 ? 0 : 1; }
function ms_unread($d, $me, $other) {
    $c = $d['c'][ms_key($me, $other)] ?? null; if (!$c) return 0;
    $s = ms_side($me, $other); $r = $c['r'][$s] ?? 0; $n = 0;
    foreach ($c['m'] as $m) if ($m['f'] !== $s && $m['i'] > $r) $n++;
    return $n;
}
function fr_read_user($name) {
    global $ST;
    return $ST->get('user', st_user_key($name));
}

function fr_action($act, $in) {
    global $ST;
    [$afp, $u] = ac_auth($in);
    if (!$u) fail('Jelentkezz be.', ['auth' => 1]);
    $me = $u['name'];

    if ($act === 'fr_list') {
        if (time() - ($u['seen'] ?? 0) > 45) { $u['seen'] = time(); save_room($afp, $u); } else release($afp);
        $msgs = $ST->get('doc', 'msgs') ?: ['c' => []];
        $F = []; $I = []; $O = []; $req = 0; $unread = 0;
        foreach ($u['fr'] ?? [] as $k => $e) {
            if ($e['st'] === 'friend') {
                $o = fr_read_user($e['n']);
                $n = ms_unread($msgs, $me, $e['n']); $unread += $n;
                $F[] = ['name' => $e['n'], 'fav' => !empty($e['fav']), 'online' => $o && time() - ($o['seen'] ?? 0) < ONLINE_SECS, 'unread' => $n];
            } elseif ($e['st'] === 'in') { $I[] = ['name' => $e['n']]; $req++; }
            else $O[] = ['name' => $e['n']];
        }
        usort($F, fn($a, $b) => [$b['fav'], $b['online'], mb_strtolower($a['name'])] <=> [$a['fav'], $a['online'], mb_strtolower($b['name'])]);
        $B = array_values(array_map(fn($e) => ['name' => $e['n']], $u['bl'] ?? []));
        out(['ok' => 1, 'friends' => $F, 'in' => $I, 'out' => $O, 'bl' => $B, 'req' => $req, 'unread' => $unread] + (mod_is_admin($u['name']) ? ['adm' => mod_pending()] : []));
    }
    release($afp);
    $name = ac_clean_name($in['name'] ?? $in['to'] ?? $in['with'] ?? '');
    if ($name === '' || !ac_exists($name)) fail('Nincs ilyen regisztrált játékos.');
    $o = fr_read_user($name); $name = $o['name']; $ko = ac_key($name);

    switch ($act) {
    case 'fr_req':
        $auto = fr_pair($me, $name, function (&$a, &$b) use ($me, $name, $ko) {
            $km = ac_key($me);
            if (isset($b['bl'][$km])) return 'Ennek a játékosnak nem küldhetsz kérést.';
            if (isset($a['bl'][$ko])) return 'Előbb oldd fel a tiltást.';
            $st = $a['fr'][$ko]['st'] ?? '';
            if ($st === 'friend') return 'Már barátok vagytok.';
            if ($st === 'out') return 'A kérés már el van küldve.';
            if (count($a['fr'] ?? []) >= FR_MAX || count($b['fr'] ?? []) >= FR_MAX) return 'Elérted a barátok maximális számát.';
            if ($st === 'in') { $a['fr'][$ko]['st'] = 'friend'; $b['fr'][$km]['st'] = 'friend'; return true; }
            $a['fr'][$ko] = ['n' => $name, 'st' => 'out', 'ts' => time()];
            $b['fr'][$km] = ['n' => $me, 'st' => 'in', 'ts' => time()];
            return false;
        });
        out(['ok' => 1, 'auto' => $auto === true]);
    case 'fr_acc': case 'fr_dec': case 'fr_cancel': case 'fr_del':
        fr_pair($me, $name, function (&$a, &$b) use ($act, $me, $ko) {
            $km = ac_key($me);
            $need = ['fr_acc' => 'in', 'fr_dec' => 'in', 'fr_cancel' => 'out', 'fr_del' => 'friend'][$act];
            if (($a['fr'][$ko]['st'] ?? '') !== $need) return 'Ez a kapcsolat már nem érvényes.';
            if ($act === 'fr_acc') { $a['fr'][$ko]['st'] = 'friend'; $b['fr'][$km]['st'] = 'friend'; }
            else { unset($a['fr'][$ko], $b['fr'][$km]); }
            return true;
        });
        out(['ok' => 1]);
    case 'fr_fav': {
        [$fp, $a] = ac_lock($me);
        if (($a['fr'][$ko]['st'] ?? '') !== 'friend') { release($fp); fail('Ez a kapcsolat már nem érvényes.'); }
        $a['fr'][$ko]['fav'] = empty($a['fr'][$ko]['fav']) ? 1 : 0;
        save_room($fp, $a); out(['ok' => 1]);
    }
    case 'bl_add':
        fr_pair($me, $name, function (&$a, &$b) use ($me, $name, $ko) {
            $km = ac_key($me);
            if (count($a['bl'] ?? []) >= FR_MAX) return 'Elérted a tiltólista maximális méretét.';
            unset($a['fr'][$ko], $b['fr'][$km]);
            $a['bl'][$ko] = ['n' => $name, 'ts' => time()];
            return true;
        });
        // a függő kihívásaik is megszűnnek
        out(['ok' => 1]);
    case 'bl_del': {
        [$fp, $a] = ac_lock($me);
        unset($a['bl'][$ko]);
        save_room($fp, $a); out(['ok' => 1]);
    }
    case 'msg_send': {
        $text = trim(preg_replace('/\s+/u', ' ', (string)($in['text'] ?? '')));
        $text = mb_substr($text, 0, MSG_LEN);
        if ($text === '') fail('Írj be egy üzenetet.');
        [$text, $filtered] = pf_clean($text);
        if (($u['fr'][$ko]['st'] ?? '') !== 'friend') fail('Csak barátnak írhatsz üzenetet.');
        if (isset($o['bl'][ac_key($me)])) fail('Ez a játékos nem fogad üzenetet.');
        [$fp, $d] = ms_open();
        $k = ms_key($me, $name);
        $c = $d['c'][$k] ?? ['m' => [], 'r' => [0, 0], 'n' => 0];
        $c['n']++;
        $s = ms_side($me, $name);
        $c['m'][] = ['i' => $c['n'], 'f' => $s, 't' => $text, 'ts' => time()];
        $c['r'][$s] = $c['n'];
        if (count($c['m']) > MSG_MAX) $c['m'] = array_slice($c['m'], -MSG_MAX);
        $d['c'][$k] = $c;
        save_room($fp, $d);
        out(['ok' => 1, 'filtered' => $filtered ? 1 : 0]);
    }
    case 'msg_get': {
        if (($u['fr'][$ko]['st'] ?? '') !== 'friend') fail('Csak barátokkal van beszélgetés.');
        [$fp, $d] = ms_open();
        $k = ms_key($me, $name); $s = ms_side($me, $name);
        $c = $d['c'][$k] ?? ['m' => [], 'r' => [0, 0], 'n' => 0];
        if (($c['r'][$s] ?? 0) < $c['n']) { $c['r'][$s] = $c['n']; $d['c'][$k] = $c; save_room($fp, $d); } else release($fp);
        $res = array_map(fn($m) => ['mine' => $m['f'] === $s, 't' => $m['t'], 'ts' => $m['ts']], array_slice($c['m'], -60));
        out(['ok' => 1, 'msgs' => $res, 'online' => time() - ($o['seen'] ?? 0) < ONLINE_SECS]);
    }
    }
    fail('Ismeretlen művelet.');
}
