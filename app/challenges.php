<?php
// Darts számláló – kihívások: egy regisztrált játékos meghív egy másikat egy meccsre.
// Elfogadáskor a szerver létrehoz egy szobát (véletlen PIN-nel), amelybe mindketten be tudnak lépni.

const CH_PENDING_TTL = 24 * 3600;
const CH_DONE_TTL    = 3600;
const CH_MAX_OUT     = 5;

function ch_open() {
    global $ST;
    [$fp, $d] = $ST->lock('doc', 'challenges', true);
    if (!is_array($d) || !isset($d['l'])) $d = ['l' => []];
    $now = time();
    $d['l'] = array_values(array_filter($d['l'], function ($c) use ($now) {
        return $c['st'] === 'pending' ? $now - $c['ts'] < CH_PENDING_TTL : $now - $c['ts'] < CH_DONE_TTL;
    }));
    return [$fp, $d];
}
function ch_view($c, $me) {
    $v = ['id' => $c['id'], 'from' => $c['from'], 'to' => $c['to'], 'st' => $c['st'], 'ago' => time() - $c['ts'], 'label' => $c['cfg']['label'] ?? ''];
    if ($c['st'] === 'accepted' && ac_key($c['from']) === ac_key($me)) { $v['room'] = $c['room']; $v['pin'] = $c['pin']; }
    return $v;
}
function ch_action($act, $in) {
    [$afp, $u] = ac_auth($in);
    if (!$u) fail('Jelentkezz be a kihíváshoz.', ['auth' => 1]);
    release($afp);
    $me = $u['name'];

    if ($act === 'chal_list') {
        [$fp, $d] = ch_open(); save_room($fp, $d);
        $k = ac_key($me); $i = []; $o = [];
        foreach ($d['l'] as $c) {
            if (ac_key($c['to']) === $k && $c['st'] === 'pending') $i[] = ch_view($c, $me);
            if (ac_key($c['from']) === $k) $o[] = ch_view($c, $me);
        }
        out(['ok' => 1, 'in' => $i, 'out' => $o]);
    }
    if ($act === 'chal_send') {
        $to = ac_clean_name($in['to'] ?? '');
        if ($to === '' || !ac_exists($to)) fail('Nincs ilyen regisztrált játékos.');
        [$tfp, $tu] = ac_lock($to); release($tfp); $to = $tu['name'];
        if (ac_key($to) === ac_key($me)) fail('Magadat nem hívhatod ki.');
        if (isset($tu['bl'][ac_key($me)])) fail('Ez a játékos nem fogad kihívást.');
        if (isset($u['bl'][ac_key($to)])) fail('Előbb oldd fel a tiltást.');
        $cfg = clean_cfg($in['cfg'] ?? null);
        if ($cfg === null) fail('Hibás játékbeállítás.');
        $first = in_array($in['first'] ?? 'me', ['me', 'other', 'rnd'], true) ? $in['first'] : 'me';
        [$fp, $d] = ch_open();
        $cnt = 0;
        foreach ($d['l'] as $c) {
            if (ac_key($c['from']) !== ac_key($me) || $c['st'] !== 'pending') continue;
            $cnt++;
            if (ac_key($c['to']) === ac_key($to)) { release($fp); fail('Már van függő kihívásod ennek a játékosnak.'); }
        }
        if ($cnt >= CH_MAX_OUT) { release($fp); fail('Legfeljebb ' . CH_MAX_OUT . ' függő kihívásod lehet.'); }
        $d['l'][] = ['id' => bin2hex(random_bytes(5)), 'from' => $me, 'to' => $to, 'cfg' => $cfg, 'first' => $first, 'ts' => time(), 'st' => 'pending'];
        save_room($fp, $d);
        out(['ok' => 1]);
    }
    $id = (string)($in['id'] ?? '');
    [$fp, $d] = ch_open();
    $idx = -1;
    foreach ($d['l'] as $k => $c) if ($c['id'] === $id) $idx = $k;
    if ($idx < 0) { save_room($fp, $d); fail('A kihívás már nem érvényes.', ['gone' => 1]); }
    $c = $d['l'][$idx];
    $isTo = ac_key($c['to']) === ac_key($me); $isFrom = ac_key($c['from']) === ac_key($me);
    if ($act === 'chal_decline') {
        if (!$isTo || $c['st'] !== 'pending') { save_room($fp, $d); fail('A kihívás már nem érvényes.', ['gone' => 1]); }
        $d['l'][$idx]['st'] = 'declined'; $d['l'][$idx]['ts'] = time();
        save_room($fp, $d); out(['ok' => 1]);
    }
    if ($act === 'chal_cancel') {
        if (!$isFrom) { save_room($fp, $d); fail('Ez nem a te kihívásod.'); }
        array_splice($d['l'], $idx, 1);
        save_room($fp, $d); out(['ok' => 1]);
    }
    if ($act === 'chal_accept') {
        if (!$isTo || $c['st'] !== 'pending') { save_room($fp, $d); fail('A kihívás már nem érvényes.', ['gone' => 1]); }
        if (count(all_rooms()) >= MAX_ROOMS) { save_room($fp, $d); fail('Betelt a szobák száma, próbáld később.'); }
        $rid = bin2hex(random_bytes(4)); $salt = bin2hex(random_bytes(8)); $pin = (string)random_int(100000, 999999);
        $first = $c['first'] === 'other' ? 1 : ($c['first'] === 'rnd' ? random_int(0, 1) : 0);
        $cfg = $c['cfg']; $cfg['first'] = $first; $cfg['names'] = [$c['from'], $c['to']];
        $base = mb_substr('⚔ ' . $c['from'] . ' – ' . $c['to'], 0, 30); $nm = $base; $n = 1;
        $names = array_map(fn($r) => mb_strtolower($r['name']), all_rooms());
        while (in_array(mb_strtolower($nm), $names, true)) { $n++; $nm = mb_substr($base, 0, 27) . ' ' . $n; }
        $room = [
            'id' => $rid, 'name' => $nm, 'salt' => $salt, 'hash' => pinHash($salt, $pin),
            'created' => time(), 'updated' => time(), 'cfg' => $cfg,
            'players' => [['name' => $c['from'], 'seen' => time(), 'acc' => true], ['name' => $c['to'], 'seen' => time(), 'acc' => true]],
            'v' => 2, 'gen' => 0, 'turns' => [], 'status' => 'playing', 'sum' => '',
            'edit' => ['v' => 0, 'by' => -1, 'note' => ''], 'del' => null, 'forfeit' => null,
        ];
        $GLOBALS['ST']->put('room', st_room_key($rid), $room);
        $d['l'][$idx]['st'] = 'accepted'; $d['l'][$idx]['room'] = $rid; $d['l'][$idx]['pin'] = $pin; $d['l'][$idx]['ts'] = time();
        save_room($fp, $d);
        out(['ok' => 1, 'seat' => 1, 'pin' => $pin, 'room' => full_view($room)]);
    }
    save_room($fp, $d);
    fail('Ismeretlen művelet.');
}
