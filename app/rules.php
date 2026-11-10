<?php
// Darts számláló – szabályellenőrző (X01 és Cricket). A böngészős motor PHP-s mása:
// végigjátssza a dobáslistát, és hibát ad, ha valami szabálytalan.
// rules_check($cfg, $turns) => ['ok'=>bool, 'err'=>string, 'done'=>bool, 'winner'=>int, 'legs'=>[...]]

function rl_need($n, $m) { $n = max(1, (int)$n); return $m === 'bo' ? intdiv($n, 2) + 1 : $n; }

function rl_x01_sets($cfg) {
    $dbl = ($cfg['outM'] ?? 'd') !== 's';
    $D = []; for ($k = 1; $k <= 20; $k++) $D[] = 2 * $k; $D[] = 50;
    $T = []; for ($k = 1; $k <= 20; $k++) $T[] = 3 * $k;
    $inM = $cfg['inM'] ?? 's'; $outM = $cfg['outM'] ?? 'd';
    $dvs = $outM === 'm' ? array_merge($D, $T) : $D;
    $ent = $inM === 'm' ? array_merge($D, $T) : $D;
    $vals = array_merge($T, $D, range(1, 20), [50, 25]);
    $vals0 = array_merge([0], $vals);
    $open = [];
    foreach ($ent as $e) foreach ($vals0 as $a) foreach ($vals0 as $b) $open[$e + $a + $b] = true;
    $outset = [];
    foreach ($dvs as $l) {
        $outset[$l] = true;
        foreach ($vals0 as $a) { $outset[$l + $a] = true; foreach ($vals0 as $b) $outset[$l + $a + $b] = true; }
    }
    return ['dbl' => $dbl, 'din' => $inM !== 's', 'open' => $open, 'outset' => $outset, 'outmax' => $outM === 'm' ? 180 : 170];
}

function rl_x01($cfg, $turns) {
    static $IMP = [163, 166, 169, 172, 173, 175, 176, 178, 179];
    $R = rl_x01_sets($cfg);
    $mode = (int)($cfg['mode'] ?? 501) ?: 501;
    $hc = $cfg['hc'] ?? ['', ''];
    $p = [];
    for ($i = 0; $i < 2; $i++) {
        $st = min(1001, max(2, (int)($hc[$i] ?? 0) ?: $mode));
        $p[] = ['st' => $st, 'r' => $st, 'o' => !$R['din'], 'l' => 0, 's' => 0, 'tl' => 0];
    }
    $NL = rl_need($cfg['legs'] ?? 1, $cfg['legsm'] ?? 'ft');
    $NS = rl_need($cfg['sets'] ?? 1, $cfg['setsm'] ?? 'ft');
    $f = (int)($cfg['first'] ?? 0) === 1 ? 1 : 0;
    $c = $f; $ls = $f; $w = -1; $lg = 0; $own = [];
    foreach ($turns as $k => $t) {
        if ($w >= 0) return ['ok' => false, 'err' => 'A meccs már véget ért.'];
        if (!is_array($t) || !isset($t['v']) || !is_int($t['v'])) return ['ok' => false, 'err' => 'Hibás kör (' . ($k + 1) . ').'];
        $v = $t['v'];
        if ($v < 0 || $v > 180 || in_array($v, $IMP, true)) return ['ok' => false, 'err' => "$v három nyíllal nem dobható."];
        foreach (['d', 'dd', 'e'] as $f2) {
            if (isset($t[$f2]) && (!is_int($t[$f2]) || $t[$f2] < 0 || $t[$f2] > 3)) return ['ok' => false, 'err' => 'Hibás nyíl-adat.'];
        }
        $own[$k] = $c;
        $q = &$p[$c];
        $rb = $q['r']; $u = !$q['o']; $r = $rb - $v;
        $fb = !empty($t['fb']);
        if ($u && $v > 0 && !isset($R['open'][$v]) && !$fb) return ['ok' => false, 'err' => "$v beszállás után nem dobható."];
        if ($u && $v > 0) $q['o'] = true;
        $canOut = !$R['dbl'] || $v < 2 || isset($R['outset'][$v]);
        $b = $fb || $r < 0 || ($R['dbl'] && ($r == 1 || ($r == 0 && !$canOut)));
        if (!$b && $r == 0) { $fin = true; } else $fin = false;
        if ($b) { $c = ($c + 1) % 2; unset($q); continue; }
        $q['r'] = $r;
        if ($fin) {
            $q['l']++; $q['tl']++;
            $set = false;
            if ($q['l'] >= $NL) { $q['s']++; $set = true; $p[0]['l'] = 0; $p[1]['l'] = 0; }
            if ($q['s'] >= $NS) { $w = $c; unset($q); continue; }
            $p[0]['r'] = $p[0]['st']; $p[1]['r'] = $p[1]['st'];
            $p[0]['o'] = $p[1]['o'] = !$R['din'];
            $ls = ($ls + 1) % 2; $c = $ls; $lg++;
            unset($q); continue;
        }
        $c = ($c + 1) % 2;
        unset($q);
    }
    return ['ok' => true, 'done' => $w >= 0, 'winner' => $w, 'legs' => [$p[0]['l'], $p[1]['l']], 'sets' => [$p[0]['s'], $p[1]['s']], 'tl' => [$p[0]['tl'], $p[1]['tl']], 'ns' => $NS, 'own' => $own];
}

function rl_ck_nums($cfg) {
    $n = $cfg['nums'] ?? null;
    if (!is_array($n) || !count($n) || count($n) > 21) return null;
    $seen = [];
    foreach ($n as $x) {
        if (!is_int($x) || !(($x >= 1 && $x <= 20) || $x === 25) || isset($seen[$x])) return null;
        $seen[$x] = true;
    }
    return array_values($n);
}

function rl_ck($cfg, $turns) {
    $nums = rl_ck_nums($cfg);
    if ($nums === null) return ['ok' => false, 'err' => 'Hibás Cricket-számok.'];
    $cks = $cfg['cks'] ?? 'std';
    if (!in_array($cks, ['std', 'cut', 'none'], true)) return ['ok' => false, 'err' => 'Hibás Cricket-mód.'];
    $hc = $cfg['hc'] ?? ['', ''];
    $u = [];
    $init = function () use (&$u, $nums, $hc) {
        for ($i = 0; $i < 2; $i++) {
            $m = []; $tot = max(0, (int)($hc[$i] ?? 0));
            foreach ($nums as $n) { $g = min(3, $tot); if ($g > 0) $m[$n] = $g; $tot -= $g; if ($tot <= 0) break; }
            $u[$i]['m'] = $m; $u[$i]['pt'] = 0;
        }
    };
    $u = [['l' => 0, 's' => 0, 'tl' => 0], ['l' => 0, 's' => 0, 'tl' => 0]];
    $init();
    $NL = rl_need($cfg['legs'] ?? 1, $cfg['legsm'] ?? 'ft');
    $NS = rl_need($cfg['sets'] ?? 1, $cfg['setsm'] ?? 'ft');
    $f = (int)($cfg['first'] ?? 0) === 1 ? 1 : 0;
    $c = $f; $ls = $f; $w = -1; $own = [];
    $nT = count($turns);
    foreach ($turns as $k => $t) {
        if ($w >= 0) return ['ok' => false, 'err' => 'A meccs már véget ért.'];
        if (!is_array($t) || !isset($t['i']) || !is_int($t['i']) || !isset($t['d']) || !is_array($t['d'])) return ['ok' => false, 'err' => 'Hibás kör.'];
        if ($t['i'] !== $c) return ['ok' => false, 'err' => 'Nem ő következik.'];
        $own[$k] = $c;
        if (count($t['d']) < 1) return ['ok' => false, 'err' => 'Üres kör.'];
        if (count($t['d']) > 3) return ['ok' => false, 'err' => 'Egy körben legfeljebb 3 nyíl lehet.'];
        $ended = false;
        foreach ($t['d'] as $d) {
            if ($ended) return ['ok' => false, 'err' => 'A leg már véget ért.'];
            if (!is_array($d)) return ['ok' => false, 'err' => 'Hibás nyíl.'];
            $n = $d['n'] ?? 0; $m = $d['m'] ?? 1;
            if (!is_int($n) || !is_int($m)) return ['ok' => false, 'err' => 'Hibás nyíl.'];
            if ($n === 0) { $m = 0; }
            else {
                if (!(($n >= 1 && $n <= 20) || $n === 25)) return ['ok' => false, 'err' => 'Hibás szám.'];
                if ($m < 1 || $m > ($n === 25 ? 2 : 3)) return ['ok' => false, 'err' => 'Hibás szorzó.'];
            }
            // találat
            if ($n && in_array($n, $nums, true)) {
                $have = $u[$c]['m'][$n] ?? 0; $add = min($m, 3 - $have); $ex = $m - $add;
                $u[$c]['m'][$n] = $have + $add;
                if ($ex > 0 && $cks !== 'none') {
                    $o = 1 - $c;
                    if (($u[$o]['m'][$n] ?? 0) < 3) {
                        if ($cks === 'std') $u[$c]['pt'] += $ex * $n; else $u[$o]['pt'] += $ex * $n;
                    }
                }
            }
            // győzelem?
            $all = true; foreach ($nums as $x) if (($u[$c]['m'][$x] ?? 0) < 3) { $all = false; break; }
            $won = false; $o = 1 - $c;
            if ($all) {
                if ($cks === 'std') $won = $u[$c]['pt'] >= $u[$o]['pt'];
                elseif ($cks === 'cut') $won = $u[$c]['pt'] <= $u[$o]['pt'];
                else $won = true;
            }
            if ($won) {
                $ended = true;
                $u[$c]['l']++; $u[$c]['tl']++;
                if ($u[$c]['l'] >= $NL) { $u[$c]['s']++; $u[0]['l'] = $u[1]['l'] = 0; }
                if ($u[$c]['s'] >= $NS) { $w = $c; }
                else { $ls = ($ls + 1) % 2; $c = $ls; $init(); continue 2; }
            }
        }
        if ($w >= 0) continue;
        $c = 1 - $c;
    }
    return ['ok' => true, 'done' => $w >= 0, 'winner' => $w, 'legs' => [$u[0]['l'], $u[1]['l']], 'sets' => [$u[0]['s'], $u[1]['s']], 'tl' => [$u[0]['tl'], $u[1]['tl']], 'ns' => $NS, 'own' => $own];
}

function rules_check($cfg, $turns) {
    if (!is_array($cfg) || !is_array($turns)) return ['ok' => false, 'err' => 'Hibás adat.'];
    $turns = array_values($turns);
    if (($cfg['gm'] ?? '') === 'ck') return rl_ck($cfg, $turns);
    return rl_x01($cfg, $turns);
}

// Ki dobhatja az új / módosított köröket? Csak a küldő saját köre: a lista végére fűzhet, vagy a saját utolsó körét javíthatja / vonhatja vissza.
// A küldő nem vihet be pontot az ellenfél helyett (a kör gazdáját a szabálymotor a sorrendből számolja, nem a kliens adatából).
function rl_norm($cfg, $t) {
    if (!is_array($t)) return null;
    if (($cfg['gm'] ?? '') === 'ck') return [$t['i'] ?? null, array_map(fn($d) => is_array($d) ? [$d['n'] ?? 0, $d['m'] ?? 1] : null, is_array($t['d'] ?? null) ? $t['d'] : [])];
    return [$t['v'] ?? null, $t['d'] ?? null, $t['dd'] ?? null, $t['e'] ?? null, !empty($t['fb'])];
}
function rl_push_auth($cfg, $old, $new, $ownNew, $seat) {
    $old = array_values(is_array($old) ? $old : []); $new = array_values($new);
    $n = max(count($old), count($new)); $k0 = -1;
    for ($k = 0; $k < $n; $k++) {
        $a = $k < count($old) ? json_encode(rl_norm($cfg, $old[$k])) : null;
        $b = $k < count($new) ? json_encode(rl_norm($cfg, $new[$k])) : null;
        if ($a !== $b) { $k0 = $k; break; }
    }
    if ($k0 < 0) return '';
    if ($k0 < count($old) - 1) return 'Csak az utolsó kör javítható.';
    if ($k0 < count($new)) {
        for ($k = $k0; $k < count($new); $k++) if (($ownNew[$k] ?? -1) !== $seat) return 'Csak a saját körödet viheted be.';
        return '';
    }
    // csak törlés: a legutolsó (törölt) körnek a küldőé kellett lennie
    $o = $old[$k0]; if (($cfg['gm'] ?? '') === 'ck') return (int)($o['i'] ?? -1) === $seat ? '' : 'Csak a saját körödet vonhatod vissza.';
    $chkOld = rules_check($cfg, $old);
    return $chkOld['ok'] && (($chkOld['own'][$k0] ?? -1) === $seat) ? '' : 'Csak a saját körödet vonhatod vissza.';
}
