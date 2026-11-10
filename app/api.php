<?php
// Darts számláló – online szoba-szerver (1v1, X01 és Cricket)
// Egyetlen fájl, adatbázis nélkül: minden szoba egy JSON fájl a darts-data mappában.
// A szerver minden beküldött dobáslistát végigjátszik a rules.php szabályaival, és a szabálytalant elutasítja.

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/ranking.php';
require_once __DIR__ . '/challenges.php';
require_once __DIR__ . '/friends.php';
require_once __DIR__ . '/moderation.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

const MAX_ROOMS   = 200;           // legfeljebb ennyi szoba lehet egyszerre
const ROOM_TTL    = 30 * 60;       // ennyi inaktivitás (senki nem kér le adatot) után a szoba törlődik (mp)
const FORFEIT_TTL = 10 * 60;    // feladott meccs szobája ennyi idő után biztosan törlődik (mp)
const MAX_TURNS   = 3000;          // egy meccs legfeljebb ennyi bejegyzés
const MAX_BODY    = 400000;        // kérés maximális mérete (bájt)

require_once __DIR__ . '/core.php';

$raw = file_get_contents('php://input');
if (strlen($raw) > MAX_BODY) fail('Túl nagy kérés.');
$in = $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($in)) $in = [];
$act = $_GET['a'] ?? '';

function pinHash($salt, $pin) { return hash('sha256', $salt . '|' . $pin); }
// a játékbeállítás megtisztítása: csak ismert mezők, ellenőrzött értékekkel
function clean_cfg($c) {
    if (!is_array($c) || !in_array($c['gm'] ?? '', ['x01', 'ck'], true)) return null;
    $iv = fn($x, $a, $b, $d) => max($a, min($b, (int)$x ?: $d));
    $m = fn($x, $ok, $d) => in_array($x, $ok, true) ? $x : $d;
    $hc = is_array($c['hc'] ?? null) ? $c['hc'] : ['', ''];
    $o = [
        'gm' => $c['gm'],
        'mode' => $iv($c['mode'] ?? 501, 2, 1001, 501),
        'inM' => $m($c['inM'] ?? 's', ['s', 'd', 'm'], 's'),
        'outM' => $m($c['outM'] ?? 'd', ['s', 'd', 'm'], 'd'),
        'legs' => $iv($c['legs'] ?? 1, 1, 99, 1), 'legsm' => $m($c['legsm'] ?? 'ft', ['ft', 'bo'], 'ft'),
        'sets' => $iv($c['sets'] ?? 1, 1, 99, 1), 'setsm' => $m($c['setsm'] ?? 'ft', ['ft', 'bo'], 'ft'),
        'cks' => $m($c['cks'] ?? 'std', ['std', 'cut', 'none'], 'std'),
        'ckn' => $m($c['ckn'] ?? 'c', ['c', 't', 'r', 'u'], 'c'),
        'hc' => [$hc[0] ?? '', $hc[1] ?? ''],
        'first' => ((int)($c['first'] ?? 0)) === 1 ? 1 : 0,
        'label' => clean($c['label'] ?? '', 120),
    ];
    foreach ($o['hc'] as &$h) $h = (string)max(0, min(1001, (int)$h)) === '0' ? '' : (string)max(0, min(1001, (int)$h));
    unset($h);
    if (is_array($c['cku'] ?? null)) $o['cku'] = array_values(array_filter(array_map('intval', $c['cku']), fn($x) => ($x >= 1 && $x <= 20) || $x === 25));
    if ($o['gm'] === 'ck') {
        $o['nums'] = rl_ck_nums($c);
        if ($o['nums'] === null) return null;
    }
    foreach (['legs', 'sets'] as $k) if ($o[$k . 'm'] === 'bo' && $o[$k] % 2 == 0) $o[$k]++;
    return $o;
}
// a játékos neve: bejelentkezve a fiók neve; egyébként regisztrált nevet nem lehet használni
function seat_identity($in, $pname, $fp = null) {
    if (!empty($in['token'])) {
        $acc = ac_verify($in);
        if ($acc === null) { if ($fp) release($fp); fail('Jelentkezz be újra.', ['auth' => 1]); }
        return [mb_substr($acc, 0, 20), true];
    }
    if ($pname !== '' && ac_exists($pname)) { if ($fp) release($fp); fail('Ez a név regisztrált fiókhoz tartozik – jelentkezz be vele.'); }
    return [$pname, false];
}
function clean($s, $max) { $s = trim(preg_replace('/\s+/u', ' ', (string)$s)); return mb_substr($s, 0, $max); }

// szoba beolvasása és zárolása; a visszaadott érték: [$fp, $room]
function lock_room($id) {
    global $ST; $k = st_room_key($id);
    [$h, $room] = $ST->lock('room', $k);
    if (!$h) return [null, null];
    if (!is_array($room)) { $ST->release($h); return [null, null]; }
    if (expired($room)) { $ST->release($h); $ST->del('room', $k); return [null, null]; }
    return [$h, $room];
}


function players_view($room) {
    $now = time(); $r = [];
    foreach ($room['players'] as $p) {
        $r[] = $p ? ['name' => $p['name'], 'ago' => $now - $p['seen'], 'left' => !empty($p['left']), 'acc' => !empty($p['acc'])] : null;
    }
    return $r;
}
function full_view($room) {
    return [
        'id' => $room['id'], 'name' => $room['name'], 'v' => $room['v'], 'gen' => $room['gen'],
        'cfg' => $room['cfg'], 'turns' => $room['turns'], 'status' => $room['status'],
        'players' => players_view($room), 'edit' => $room['edit'], 'sum' => $room['sum'],
        'del' => $room['del'] ?? null, 'forfeit' => $room['forfeit'] ?? null,
    ];
}
function check_pin($room, $in) {
    return isset($in['pin']) && hash_equals($room['hash'], pinHash($room['salt'], (string)$in['pin']));
}
// PIN-ellenőrzés kitalálás ellen védve: 5 percen belül 8 hibás próbálkozás után a szoba ideiglenesen zárolt
function pin_guard($fp, &$room, $in) {
    $now = time();
    $bad = array_values(array_filter($room['bad'] ?? [], fn($t) => $now - $t < 300));
    if (count($bad) >= 8) { release($fp); fail('Túl sok hibás PIN. Próbáld újra néhány perc múlva.'); }
    if (check_pin($room, $in)) return;
    $bad[] = $now; $room['bad'] = $bad;
    save_room($fp, $room);
    release($fp);
    fail('Hibás PIN.');
}
function expired($r) {
    if (time() - last_active($r) > ROOM_TTL) return true;
    return ($r['status'] ?? '') === 'forfeit' && time() - $r['updated'] > FORFEIT_TTL;
}
function last_active($r) {
    $t = $r['updated'];
    foreach ($r['players'] as $p) { if ($p && $p['seen'] > $t) $t = $p['seen']; }
    return $t;
}
function all_rooms() {
    global $ST;
    $res = [];
    foreach ($ST->all('room') as $k => $r) {
        if (expired($r)) { $ST->del('room', $k); continue; }
        $res[] = $r;
    }
    return $res;
}

if (in_array($act, ['rank', 'prof'], true)) rk_action($act, $in);
if (in_array($act, ['fr_list', 'fr_req', 'fr_acc', 'fr_dec', 'fr_cancel', 'fr_del', 'fr_fav', 'bl_add', 'bl_del', 'msg_send', 'msg_get'], true)) fr_action($act, $in);
if (in_array($act, ['chal_list', 'chal_send', 'chal_accept', 'chal_decline', 'chal_cancel'], true)) ch_action($act, $in);
if ($act === 'pcheck') {   // mely nevek regisztrált online fiókok (a helyi játékosok jelölésére)
    $names = is_array($in['names'] ?? null) ? array_slice($in['names'], 0, 60) : [];
    $reg = [];
    foreach ($names as $n) { $n = ac_clean_name($n); if ($n !== '' && $ST->has('user', st_user_key($n))) $reg[] = $n; }
    out(['ok' => 1, 'reg' => $reg]);
}
if ($act === 'cfg') out(array_merge(['ok' => 1], club_cfg()));
if ($act === 'pres') pres_action($in);
if (in_array($act, ['report', 'ac_delete', 'adm_list', 'adm_act', 'adm_club_get', 'adm_club_set', 'adm_users', 'adm_user_add', 'adm_pw', 'adm_admin', 'adm_logo', 'adm_logo_del', 'adm_upd_check', 'adm_upd_apply'], true)) mod_action($act, $in);
if (in_array($act, ['reg', 'login', 'recover', 'newcode', 'chpw', 'pwreq', 'appeal', 'logout', 'hsync', 'me'], true)) ac_action($act, $in);

switch ($act) {

case 'storage': out(['ok' => 1, 'outside' => $DIR_OUTSIDE ? 1 : 0, 'db' => $ST->kind()]);

case 'list': {
    $rooms = all_rooms();
    usort($rooms, fn($a, $b) => $b['updated'] <=> $a['updated']);
    $l = [];
    foreach ($rooms as $r) {
        if (($r['status'] ?? '') === 'forfeit') continue;   // feladott meccs szobája nem látszik a listában
        $l[] = [
            'id' => $r['id'], 'name' => $r['name'], 'label' => $r['cfg']['label'] ?? '',
            'status' => $r['status'], 'sum' => $r['sum'], 'ago' => time() - $r['updated'],
            'players' => players_view($r),
        ];
    }
    out(['ok' => 1, 'rooms' => $l]);
}

case 'create': {
    $name = clean($in['name'] ?? '', 30);
    $pname = clean($in['player'] ?? '', 20);
    $pin = (string)($in['pin'] ?? '');
    $cfg = $in['cfg'] ?? null;
    [$pname, $accn] = seat_identity($in, $pname);
    if ($name === '' || $pname === '') fail('Add meg a szoba nevét és a saját nevedet.');
    if (pf_bad($name) || (!$accn && pf_bad($pname))) fail('Ez a név nem használható.');
    if (mb_strlen($pin) < 3 || mb_strlen($pin) > 20) fail('A PIN 3–20 karakter lehet.');
    $cfg = clean_cfg($cfg);
    if ($cfg === null) fail('Hibás játékbeállítás.');
    $rooms = all_rooms();
    if (count($rooms) >= MAX_ROOMS) fail('Betelt a szobák száma, próbáld később.');
    foreach ($rooms as $r) if (mb_strtolower($r['name']) === mb_strtolower($name)) fail('Ilyen nevű szoba már van.');
    $id = bin2hex(random_bytes(4));
    $salt = bin2hex(random_bytes(8));
    $cfg['names'] = [$pname, '…'];
    $room = [
        'id' => $id, 'name' => $name, 'salt' => $salt, 'hash' => pinHash($salt, $pin),
        'created' => time(), 'updated' => time(), 'cfg' => $cfg,
        'players' => [['name' => $pname, 'seen' => time(), 'acc' => $accn], null],
        'v' => 1, 'gen' => 0, 'turns' => [], 'status' => 'waiting', 'sum' => '',
        'edit' => ['v' => 0, 'by' => -1, 'note' => ''], 'del' => null, 'forfeit' => null,
    ];
    $ST->put('room', $id, $room);
    out(['ok' => 1, 'seat' => 0, 'room' => full_view($room)]);
}

case 'join': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba nem található.', ['gone' => 1]);
    pin_guard($fp, $room, $in);
    if (($room['status'] ?? '') === 'forfeit') { release($fp); fail('Ebben a szobában a játék véget ért (valaki feladta).'); }
    $pname = clean($in['name'] ?? '', 20);
    [$pname, $accn] = seat_identity($in, $pname, $fp);
    if ($pname === '') { release($fp); fail('Add meg a neved.'); }
    if (!$accn && pf_bad($pname)) { release($fp); fail('Ez a név nem használható.'); }
    foreach ($room['players'] as $p) {
        if ($p && !empty($p['left']) && mb_strtolower($p['name']) === mb_strtolower($pname)) { release($fp); fail('Ebből a szobából már kiléptél.'); }
    }
    $seat = -1;
    foreach ($room['players'] as $i => $p) {
        if ($p && mb_strtolower($p['name']) === mb_strtolower($pname)) $seat = $i;   // visszalépés a saját helyre
    }
    if ($seat < 0) {
        if ($room['players'][1] === null) {
            $seat = 1;
            $room['players'][1] = ['name' => $pname, 'seen' => time(), 'acc' => $accn];
            $room['cfg']['names'][1] = $pname;
            $room['status'] = 'playing';
            $room['v']++;
        } else { release($fp); fail('A szoba tele van. Visszalépéshez a korábbi nevedet add meg.'); }
    }
    $room['players'][$seat]['seen'] = time();
    $room['updated'] = time();
    $view = full_view($room);
    save_room($fp, $room);
    out(['ok' => 1, 'seat' => $seat, 'room' => $view]);
}

case 'poll': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba megszűnt.', ['gone' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    if ($seat < 0 || $seat > 1 || !$room['players'][$seat]) { release($fp); fail('Hibás hely.'); }
    $dirty = false;
    if (time() - $room['players'][$seat]['seen'] >= 4) { $room['players'][$seat]['seen'] = time(); $dirty = true; }
    $v = (int)($in['v'] ?? 0);
    $res = ['ok' => 1, 'players' => players_view($room), 'v' => $room['v']];
    if ($v !== $room['v']) { $res['room'] = full_view($room); } else { $res['same'] = 1; }
    if ($dirty) save_room($fp, $room); else release($fp);
    out($res);
}

case 'spoll': {
    // megtekintő (nézőpont): PIN nélkül, csak olvas, a játékosok jelenlétét nem módosítja
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba megszűnt.', ['gone' => 1]);
    $v = (int)($in['v'] ?? 0);
    $res = ['ok' => 1, 'players' => players_view($room), 'v' => $room['v']];
    if ($v !== $room['v']) { $res['room'] = full_view($room); } else { $res['same'] = 1; }
    release($fp);
    out($res);
}

case 'push': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba megszűnt.', ['gone' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    if ($seat < 0 || $seat > 1 || !$room['players'][$seat]) { release($fp); fail('Hibás hely.'); }
    if ((int)($in['baseV'] ?? -1) !== $room['v']) {
        $view = full_view($room); release($fp);
        out(['ok' => 0, 'conflict' => 1, 'room' => $view]);
    }
    $turns = $in['turns'] ?? null;
    if (!is_array($turns) || count($turns) > MAX_TURNS) { release($fp); fail('Hibás dobáslista.'); }
    $chk = rules_check($room['cfg'], $turns);
    if (!$chk['ok']) {
        $view = full_view($room); release($fp);
        out(['ok' => 0, 'rule' => 1, 'err' => 'Szabálytalan lépés: ' . $chk['err'], 'room' => $view]);
    }
    $au = rl_push_auth($room['cfg'], $room['turns'] ?? [], $turns, $chk['own'] ?? [], $seat);
    if ($au !== '') {
        $view = full_view($room); release($fp);
        out(['ok' => 0, 'rule' => 1, 'err' => 'Szabálytalan lépés: ' . $au, 'room' => $view]);
    }
    $room['turns'] = array_values($turns);
    $room['v']++;
    $room['updated'] = time();
    $room['players'][$seat]['seen'] = time();
    $wasDone = ($room['status'] ?? '') === 'finished';
    $room['status'] = !empty($chk['done']) ? 'finished' : 'playing';
    if (!empty($chk['done'])) rk_record($room, $chk['winner']);
    elseif ($wasDone) rk_record($room, -1);
    $room['result'] = $chk;
    $room['sum'] = clean($in['sum'] ?? '', 60);
    if (($in['kind'] ?? '') === 'edit') {
        $room['edit'] = ['v' => $room['v'], 'by' => $seat, 'note' => clean($in['note'] ?? '', 200)];
    }
    $v = $room['v'];
    save_room($fp, $room);
    out(['ok' => 1, 'v' => $v]);
}

case 'rematch': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba megszűnt.', ['gone' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    if ($seat < 0 || $seat > 1 || !$room['players'][$seat]) { release($fp); fail('Hibás hely.'); }
    $cfg = clean_cfg($in['cfg'] ?? null);
    if ($cfg === null) { release($fp); fail('Hibás beállítás.'); }
    foreach ($room['players'] as $p) { if ($p && !empty($p['left'])) { release($fp); fail('Az ellenfeled már kilépett.'); } }
    // a már futó új meccset (gen) ne indítsa újra a második kérés
    if ((int)($in['gen'] ?? -1) !== $room['gen']) {
        $view = full_view($room); release($fp);
        out(['ok' => 1, 'room' => $view, 'already' => 1]);
    }
    $cfg['names'] = $room['cfg']['names'];
    $room['cfg'] = $cfg;
    $room['gen']++;
    $room['turns'] = [];
    $room['v']++;
    $room['status'] = 'playing';
    $room['sum'] = '';
    $room['updated'] = time();
    $view = full_view($room);
    save_room($fp, $room);
    out(['ok' => 1, 'room' => $view]);
}

case 'forfeit': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba megszűnt.', ['gone' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    if ($seat < 0 || $seat > 1 || !$room['players'][$seat]) { release($fp); fail('Hibás hely.'); }
    if (($room['status'] ?? '') === 'playing') rk_record($room, 1 - $seat, true);
    $room['status'] = 'forfeit';
    $room['forfeit'] = ['by' => $seat];
    $room['players'][$seat]['left'] = true;
    $room['v']++;
    $room['updated'] = time();
    save_room($fp, $room);
    out(['ok' => 1]);
}

case 'leave': {
    // kilépés a befejezett meccsből; ha mindkét játékos kilépett, a szoba törlődik
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) out(['ok' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    if ($seat < 0 || $seat > 1 || !$room['players'][$seat]) { release($fp); fail('Hibás hely.'); }
    $room['players'][$seat]['left'] = true;
    $all = true;
    foreach ($room['players'] as $p) { if ($p && empty($p['left'])) $all = false; }
    if ($all) { release($fp); $ST->del('room', st_room_key($in['id'])); out(['ok' => 1, 'deleted' => 1]); }
    $room['updated'] = time();
    save_room($fp, $room);
    out(['ok' => 1]);
}

case 'delreq': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) fail('A szoba megszűnt.', ['gone' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    if ($seat < 0 || $seat > 1 || !$room['players'][$seat]) { release($fp); fail('Hibás hely.'); }
    $room['v']++;
    $room['del'] = ['by' => $seat, 'state' => 'pending', 'v' => $room['v']];
    $room['updated'] = time();
    save_room($fp, $room);
    out(['ok' => 1]);
}

case 'delans': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) out(['ok' => 1]);
    pin_guard($fp, $room, $in);
    $seat = (int)($in['seat'] ?? 0);
    $d = $room['del'] ?? null;
    if (!$d || $d['state'] !== 'pending' || $d['by'] === $seat) { release($fp); fail('Nincs megválaszolandó törlési kérés.'); }
    if (!empty($in['yes'])) { release($fp); $ST->del('room', st_room_key($in['id'])); out(['ok' => 1, 'deleted' => 1]); }
    $room['v']++;
    $room['del'] = ['by' => $d['by'], 'state' => 'denied', 'v' => $room['v']];
    $room['updated'] = time();
    save_room($fp, $room);
    out(['ok' => 1]);
}

case 'close': {
    [$fp, $room] = lock_room($in['id'] ?? '');
    if (!$room) out(['ok' => 1]);
    pin_guard($fp, $room, $in);
    release($fp);
    $ST->del('room', st_room_key($in['id']));
    out(['ok' => 1]);
}

default:
    fail('Ismeretlen művelet.');
}
