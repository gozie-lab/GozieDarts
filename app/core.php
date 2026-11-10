<?php
// Gozie Darts – közös indítás: konfiguráció (darts-config.php), adatmappa, tárolás, kimeneti segédfüggvények.
// A CORE_SOFT konstanssal (telepítő) hiba esetén nem JSON-t ad, hanem a $CORE_ERR változóban jelzi a problémát.

require_once __DIR__ . '/storage.php';

// Adatmappa: lehetőleg a webgyökéren KÍVÜL, hogy a fájlok (fiókok, jelszó-lenyomatok, üzenetek) böngészőből semmiképp se legyenek elérhetők.
// Sorrend: 1) darts-config.php ['data_dir'=>'/abszolút/út'], 2) DARTS_DATA környezeti változó,
// 3) a webgyökér szülőmappája/darts-data (ha írható), 4) végső tartalék: a program mappájában (.htaccess védi).
$CFG = [];
$cfgf = __DIR__ . '/darts-config.php';
if (is_file($cfgf)) { $c = @include $cfgf; if (is_array($c)) $CFG = $c; }
function data_dir_resolve() {
    global $CFG;
    $cands = [];
    if (!empty($CFG['data_dir'])) $cands[] = rtrim($CFG['data_dir'], '/\\');
    $env = getenv('DARTS_DATA'); if ($env) $cands[] = rtrim($env, '/\\');
    $dr = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    if ($dr !== '' && dirname($dr) !== $dr) {
        // minden telepítés saját mappát kap (a program helyéből képzett azonosítóval), így egy szerveren több klub sem akad össze
        $own = dirname($dr) . '/darts-data-' . substr(sha1(__DIR__), 0, 8);
        $legacy = dirname($dr) . '/darts-data';   // régi, egyetlen telepítéses elrendezés: csak ha a program a webgyökér közvetlen mappájában van
        $cands[] = (!is_dir($own) && is_dir($legacy) && realpath($dr) === realpath(__DIR__)) ? $legacy : $own;
    }
    foreach ($cands as $c) {
        if (!is_dir($c)) @mkdir($c, 0750, true);
        if (is_dir($c) && is_writable($c)) return [$c, true];
    }
    return [__DIR__ . '/darts-data', false];
}
list($DIR, $DIR_OUTSIDE) = data_dir_resolve();
// mappa létrehozása + védelem (Apache: .htaccess, minden esetben üres index.html)
function data_dir_prepare($d) {
    if (!is_dir($d)) @mkdir($d, 0750, true);
    if (!is_dir($d)) return false;
    if (!is_file($d . '/.htaccess'))
        @file_put_contents($d . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    return true;
}
data_dir_prepare($DIR);
// régi (webgyökéren belüli) adatok átköltöztetése az új helyre, egyszer
$old = __DIR__ . '/darts-data';
if ($DIR_OUTSIDE && is_dir($old) && realpath($old) !== realpath($DIR) && !is_file($DIR . '/.migrated')) {
    $mv = function ($from, $to) use (&$mv) {
        if (!is_dir($to)) @mkdir($to, 0750, true);
        foreach (scandir($from) ?: [] as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') continue;
            $p = "$from/$f";
            if (is_dir($p)) $mv($p, "$to/$f");
            elseif (!file_exists("$to/$f")) { if (@copy($p, "$to/$f")) @unlink($p); } else @unlink($p);
        }
    };
    $mv($old, $DIR);
    foreach (['users'] as $sd) @rmdir("$old/$sd");
    @file_put_contents($DIR . '/.migrated', date('c'));
}
$CORE_ERR = '';
if (!is_dir($DIR) || !is_writable($DIR)) { $CORE_ERR = 'A szerver nem tud írni az adatmappába.'; if (!defined('CORE_SOFT')) out(['ok' => 0, 'err' => $CORE_ERR]); }
$ST = null;
if ($CORE_ERR === '') { try { $ST = st_init($DIR, $CFG); } catch (Throwable $e) { $CORE_ERR = 'Az adatbázis nem érhető el.'; if (!defined('CORE_SOFT')) out(['ok' => 0, 'err' => $CORE_ERR]); } }

// a klub nyilvános arculata a konfigurációból (név, szín, logó) – ellenőrzött értékekkel
function club_clean($a) {
    $n = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($a['club'] ?? ''))), 0, 40);
    $c = (string)($a['color'] ?? ''); if (!preg_match('/^#[0-9a-fA-F]{6}$/', $c)) $c = '';
    $l = (string)($a['logo'] ?? ''); if (!preg_match('#^(https://[^\s"\'<>]+|[A-Za-z0-9._/\-]+\.(png|jpe?g|svg|webp|gif))$#i', $l)) $l = '';
    $reg = !array_key_exists('reg', $a) || !empty($a['reg']);   // nyílt regisztráció (alapból igen)
    return ['club' => $n, 'color' => strtolower($c), 'logo' => $l, 'reg' => $reg];
}
// az adminisztrációs felületen mentett beállítás (doc 'club') felülírja a darts-config.php értékeit
function club_cfg() {
    global $CFG, $ST;
    $d = $ST ? $ST->get('doc', 'club') : null;
    return club_clean(is_array($d) ? $d : $CFG);
}
// a klub beállításainak mentése (doc 'club'; felülírja a darts-config.php értékeit)
function club_save($c) {
    global $ST;
    [$fp] = $ST->lock('doc', 'club', true);
    save_room($fp, club_clean($c));
}
// feltöltött logó ellenőrzése és mentése a program mappájába; visszatér: [fájlnév|null, hibaüzenet]
function logo_save($bytes) {
    if ($bytes === '' || strlen($bytes) > 800 * 1024) return [null, 'A kép legfeljebb 800 KB lehet.'];
    $i = @getimagesizefromstring($bytes);
    $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$i[2] ?? 0] ?? null;
    if (!$ext || $i[0] > 4000 || $i[1] > 4000) return [null, 'Png, jpg, gif vagy webp kép kell (legfeljebb 4000 pixel).'];
    if (!is_writable(__DIR__)) return [null, 'A program mappája nem írható, a logót kézzel töltsd fel (a beállításban add meg a fájl nevét).'];
    foreach (glob(__DIR__ . '/logo-*.*') ?: [] as $f) @unlink($f);
    $fn = 'logo-' . substr(sha1($bytes), 0, 8) . '.' . $ext;
    if (@file_put_contents(__DIR__ . '/' . $fn, $bytes) === false) return [null, 'A logót nem sikerült elmenteni.'];
    return [$fn, ''];
}
function logo_remove() { foreach (glob(__DIR__ . '/logo-*.*') ?: [] as $f) @unlink($f); }
function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function fail($m, $extra = []) { out(array_merge(['ok' => 0, 'err' => $m], $extra)); }


// dokumentum mentése és a zárolás feloldása (szoba, fiók és egyéb dokumentumok közös kezelője)
function save_room($h, $data) { global $ST; $ST->save($h, $data); }
function release($h) { global $ST; $ST->release($h); }
