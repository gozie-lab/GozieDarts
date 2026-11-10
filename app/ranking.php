<?php
// Darts számláló – online ranglista. Csak két regisztrált játékos között, a szerver által ellenőrzött,
// befejezett (vagy feladott) online meccsek számítanak. Az ELO mindig a meccslistából számolódik újra.

const RK_START = 1000, RK_K = 32, RK_MAX = 3000, RK_MIN_TURNS = 6, RK_PAIR_DAY = 5;

function rk_open() {
    global $ST;
    [$fp, $d] = $ST->lock('doc', 'ranking', true);
    if (!is_array($d) || !isset($d['m'])) $d = ['m' => []];
    return [$fp, $d];
}
// win: 0/1 = a győztes helye, -1 = a meccs törlése (pl. javítás után már nincs győztes)
function rk_record($room, $win, $ff = false) {
    $p = $room['players'];
    $k = $room['id'] . '.' . $room['gen'];
    $ok = $win >= 0 && count($room['turns']) >= RK_MIN_TURNS && $p[0] && $p[1] && !empty($p[0]['acc']) && !empty($p[1]['acc'])
          && ac_key($p[0]['name']) !== ac_key($p[1]['name']);
    if (!$ok && $win >= 0) return;
    [$fp, $d] = rk_open();
    if (!$ok) {
        if (isset($d['m'][$k])) { unset($d['m'][$k]); save_room($fp, $d); } else release($fp);
        return;
    }
    $chk = rules_check($room['cfg'], $room['turns']);
    $old = $d['m'][$k]['ts'] ?? time();
    $d['m'][$k] = ['ts' => $old, 'a' => $p[0]['name'], 'b' => $p[1]['name'], 'w' => $win, 'gm' => $room['cfg']['gm'],
                   'lb' => $room['cfg']['label'] ?? '', 'sc' => ($chk['ok'] && !$ff) ? (($chk['ns'] > 1 ? $chk['sets'][0] . ':' . $chk['sets'][1] : '') . '|' . $chk['tl'][0] . ':' . $chk['tl'][1]) : '', 'ff' => $ff ? 1 : 0];
    if (count($d['m']) > RK_MAX) { uasort($d['m'], fn($x, $y) => $x['ts'] <=> $y['ts']); $d['m'] = array_slice($d['m'], -RK_MAX, null, true); }
    save_room($fp, $d);
}
function rk_calc() {
    global $ST;
    $d = $ST->get('doc', 'ranking');
    $ms = is_array($d) ? array_values($d['m'] ?? []) : [];
    usort($ms, fn($x, $y) => $x['ts'] <=> $y['ts']);
    $P = []; $pair = [];
    $get = function ($n) use (&$P) {
        $key = ac_key($n);
        if (!isset($P[$key])) $P[$key] = ['name' => $n, 'elo' => RK_START, 'm' => 0, 'w' => 0, 'l' => 0, 'x' => [0, 0], 'c' => [0, 0], 'rec' => []];
        $P[$key]['name'] = $n; return $key;
    };
    foreach ($ms as $m) {
        $a = $get($m['a']); $b = $get($m['b']);
        $pk = (strcmp($a, $b) < 0 ? "$a|$b" : "$b|$a") . '|' . intdiv($m['ts'], 86400);
        $pair[$pk] = ($pair[$pk] ?? 0) + 1;
        $rated = $pair[$pk] <= RK_PAIR_DAY;
        $sa = $m['w'] === 0 ? 1 : 0;
        if ($rated) {
            $ea = 1 / (1 + pow(10, ($P[$b]['elo'] - $P[$a]['elo']) / 400));
            $da = RK_K * ($sa - $ea);
            $P[$a]['elo'] += $da; $P[$b]['elo'] -= $da;
        }
        foreach ([[$a, $sa, $m['b']], [$b, 1 - $sa, $m['a']]] as $i => [$q, $s, $opp]) {
            $P[$q]['m']++; $s ? $P[$q]['w']++ : $P[$q]['l']++;
            $g = $m['gm'] === 'ck' ? 'c' : 'x';
            $P[$q][$g][$s ? 0 : 1]++;
            $mine = $i === 0 ? $m['sc'] : implode('|', array_map(fn($t) => implode(':', array_reverse(explode(':', $t))), explode('|', $m['sc'])));
            $P[$q]['rec'][] = ['ts' => $m['ts'], 'opp' => $opp, 'win' => $s, 'lb' => $m['lb'], 'sc' => $m['sc'] === '' ? '' : $mine, 'gm' => $m['gm'], 'ff' => $m['ff'], 'rated' => $rated ? 1 : 0];
        }
    }
    uasort($P, fn($x, $y) => [round($y['elo']), $y['m']] <=> [round($x['elo']), $x['m']]);
    return $P;
}
function rk_action($act, $in) {
    if ($act === 'rank') {
        $P = rk_calc(); $l = []; $i = 0;
        foreach ($P as $q) { if (mb_strpos($q['name'], DEL_PREFIX) === 0) continue; if (++$i > 100) break; $l[] = ['name' => $q['name'], 'elo' => (int)round($q['elo']), 'm' => $q['m'], 'w' => $q['w'], 'l' => $q['l']]; }
        out(['ok' => 1, 'list' => $l, 'total' => count($P)]);
    }
    if ($act === 'prof') {
        $P = rk_calc(); $key = ac_key($in['name'] ?? ''); $i = 0; $found = null;
        foreach ($P as $kk => $q) { $i++; if ($kk === $key) { $found = $q; break; } }
        if (!$found) {
            if (!ac_exists($in['name'] ?? '')) fail('Nincs ilyen játékos.');
            out(['ok' => 1, 'name' => ac_clean_name($in['name']), 'elo' => RK_START, 'rank' => 0, 'm' => 0, 'w' => 0, 'l' => 0, 'x' => [0, 0], 'c' => [0, 0], 'rec' => []]);
        }
        $rec = array_slice(array_reverse($found['rec']), 0, 15);
        out(['ok' => 1, 'name' => $found['name'], 'elo' => (int)round($found['elo']), 'rank' => $i, 'm' => $found['m'], 'w' => $found['w'], 'l' => $found['l'], 'x' => $found['x'], 'c' => $found['c'], 'rec' => $rec]);
    }
}
