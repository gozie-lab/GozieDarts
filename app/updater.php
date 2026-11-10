<?php
// GozieDarts – update from GitHub releases (admin page "Update" tab). Only the base admin can install.
// Flow: list releases (GitHub API) -> download the release zip -> verify (zip layout, file types, PHP syntax, optional SHA-256)
// -> back up the files that will be replaced -> write the new files -> roll back on any error.
// Never touched: darts-config.php, the data folder, install.php.
// Config (darts-config.php, all optional): 'update_repo' => 'owner/name', 'update_api' => 'https://api.github.com', 'update' => false (disable).

const UP_MAX_ZIP = 31457280, UP_MAX_UNPACKED = 62914560, UP_MAX_FILES = 600, UP_KEEP_BACKUPS = 3;
const UP_EXT = ['php', 'html', 'js', 'css', 'png', 'jpg', 'svg', 'ico', 'webmanifest', 'md', 'txt', 'woff', 'woff2', 'json'];
const UP_SKIP = ['darts-config.php', 'install.php'];

function up_cfg($k, $d) { global $CFG; return isset($CFG[$k]) && is_string($CFG[$k]) && $CFG[$k] !== '' ? $CFG[$k] : $d; }
function up_repo() { $r = up_cfg('update_repo', 'gozie-lab/GozieDarts'); return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $r) ? $r : 'gozie-lab/GozieDarts'; }
function up_api() { return rtrim(up_cfg('update_api', 'https://api.github.com'), '/'); }
function up_test_mode() { return strpos(up_api(), 'https://api.github.com') !== 0; }
function up_current() { $f = __DIR__ . '/VERSION'; $v = is_file($f) ? trim((string)@file_get_contents($f)) : ''; return preg_match('/^\d+(\.\d+){0,3}$/', $v) ? $v : '0'; }
function up_ver($tag) { $v = ltrim(trim((string)$tag), 'vV'); return preg_match('/^\d+(\.\d+){0,3}$/', $v) ? $v : null; }

// HTTP GET (cURL, or file_get_contents when cURL is missing). With $dest the body is written to that file.
function up_fetch($url, $max, $dest = null, $json = false) {
    $test = up_test_mode();
    if (!$test && !preg_match('#^https://#i', $url)) return ['code' => 0, 'body' => '', 'err' => 'Csak HTTPS cím engedélyezett.'];
    $hdr = ['User-Agent: GozieDarts-updater', 'Accept: ' . ($json ? 'application/vnd.github+json' : 'application/octet-stream')];
    $body = ''; $fh = $dest ? @fopen($dest, 'wb') : null; if ($dest && !$fh) return ['code' => 0, 'body' => '', 'err' => 'Az ideiglenes fájl nem írható.'];
    $size = 0; $over = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opt = [CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $hdr,
            CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$body, &$size, &$over, $max, $fh) {
                $size += strlen($d); if ($size > $max) { $over = true; return 0; }
                if ($fh) fwrite($fh, $d); else $body .= $d; return strlen($d);
            }];
        if (!$test && defined('CURLPROTO_HTTPS')) { $opt[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS; $opt[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS; }
        curl_setopt_array($ch, $opt);
        $ok = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($fh) fclose($fh);
        if ($over) return ['code' => $code, 'body' => '', 'err' => 'A letöltés túl nagy.'];
        if ($ok === false) return ['code' => $code, 'body' => '', 'err' => $err ?: 'Hálózati hiba.'];
        return ['code' => $code, 'body' => $body, 'err' => ''];
    }
    if (!ini_get('allow_url_fopen')) { if ($fh) fclose($fh); return ['code' => 0, 'body' => '', 'err' => 'A tárhelyen sem a cURL, sem az allow_url_fopen nem érhető el, ezért nem éri el a GitHubot.']; }
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $hdr), 'timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5, 'ignore_errors' => true]]);
    $s = @fopen($url, 'rb', false, $ctx);
    if (!$s) { if ($fh) fclose($fh); return ['code' => 0, 'body' => '', 'err' => 'A szerver nem érhető el.']; }
    $code = 0; foreach (($http_response_header ?? []) as $h) if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int)$m[1];
    while (!feof($s)) { $d = fread($s, 65536); if ($d === false) break; $size += strlen($d); if ($size > $max) { fclose($s); if ($fh) fclose($fh); return ['code' => $code, 'body' => '', 'err' => 'A letöltés túl nagy.']; } if ($fh) fwrite($fh, $d); else $body .= $d; }
    fclose($s); if ($fh) fclose($fh);
    return ['code' => $code, 'body' => $body, 'err' => ''];
}

// Releases (newest first), cached for 10 minutes. Returns [list, error].
function up_releases($force = false) {
    global $ST;
    $c = $ST->get('doc', 'upd');
    if (!$force && is_array($c) && time() - (int)($c['ts'] ?? 0) < 600 && ($c['repo'] ?? '') === up_repo() && ($c['api'] ?? '') === up_api()) return [$c['l'] ?? [], ''];
    $r = up_fetch(up_api() . '/repos/' . up_repo() . '/releases?per_page=20', 1500000, null, true);
    if ($r['err'] !== '') return [[], $r['err']];
    if ($r['code'] === 404) return [[], 'Nincs kiadás a tárolóban (' . up_repo() . ').'];
    if ($r['code'] !== 200) return [[], 'A GitHub HTTP ' . $r['code'] . ' választ adott.'];
    $j = json_decode($r['body'], true); if (!is_array($j)) return [[], 'Váratlan válasz a GitHubtól.'];
    $l = [];
    foreach ($j as $rel) {
        if (!is_array($rel) || !empty($rel['draft']) || !empty($rel['prerelease'])) continue;
        $v = up_ver($rel['tag_name'] ?? ''); if ($v === null) continue;
        $zip = ''; $sha = '';
        foreach (($rel['assets'] ?? []) as $a) {
            if (($a['name'] ?? '') === 'GozieDarts.zip') $zip = (string)($a['browser_download_url'] ?? '');
            if (($a['name'] ?? '') === 'GozieDarts.zip.sha256') $sha = (string)($a['browser_download_url'] ?? '');
        }
        $l[] = ['v' => $v, 'name' => mb_substr((string)($rel['name'] ?? ''), 0, 120), 'notes' => mb_substr((string)($rel['body'] ?? ''), 0, 8000), 'url' => (string)($rel['html_url'] ?? ''),
                'date' => (string)($rel['published_at'] ?? ''), 'zip' => $zip, 'sha' => $sha, 'zipball' => (string)($rel['zipball_url'] ?? '')];
    }
    usort($l, fn($a, $b) => version_compare($b['v'], $a['v']));
    $ST->put('doc', 'upd', ['ts' => time(), 'repo' => up_repo(), 'api' => up_api(), 'l' => $l]);
    return [$l, ''];
}

function up_can() {
    $w = is_writable(__DIR__) && is_writable(__DIR__ . '/index.html') && is_writable(__DIR__ . '/api.php');
    return ['zip' => class_exists('ZipArchive'), 'net' => function_exists('curl_init') || (bool)ini_get('allow_url_fopen'), 'write' => $w];
}

function up_status($force) {
    [$l, $err] = up_releases($force);
    $cur = up_current(); $newer = [];
    foreach ($l as $r) if (version_compare($r['v'], $cur, '>')) $newer[] = $r;
    $can = up_can(); $can['enabled'] = ($GLOBALS['CFG']['update'] ?? true) !== false;
    return ['ok' => 1, 'cur' => $cur, 'repo' => up_repo(), 'err' => $err, 'newer' => $newer, 'can' => $can, 'latest' => $l[0]['v'] ?? ''];
}

function up_safe_rel($p) {
    if ($p === '' || strpos($p, "\0") !== false || strpos($p, '\\') !== false || $p[0] === '/' || preg_match('#(^|/)\.\.?(/|$)#', $p) || preg_match('#(^|/)\.#', $p)) return false;
    if ($p === 'VERSION') return true;
    $e = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    return in_array($e, UP_EXT, true);
}

function up_rmtree($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) ? up_rmtree($p) : @unlink($p); }
    @rmdir($d);
}

function up_apply($in) {
    global $DIR;
    if (($GLOBALS['CFG']['update'] ?? true) === false) fail('A frissítés ki van kapcsolva a darts-config.php fájlban.');
    $can = up_can();
    if (!$can['zip']) fail('A tárhelyen hiányzik a PHP ZipArchive bővítmény, ezért itt nem telepíthető a frissítés. Frissíts a fájlok kézi feltöltésével (lásd INSTALL).');
    if (!$can['net']) fail('A tárhely nem tud kimenő HTTPS kéréseket indítani (nincs cURL / allow_url_fopen).');
    if (!$can['write']) fail('Az alkalmazás fájljait a webszerver nem tudja írni, ezért itt nem telepíthető a frissítés. Frissíts a fájlok kézi feltöltésével (lásd INSTALL).');
    $want = up_ver($in['v'] ?? ''); if ($want === null) fail('Érvénytelen verzió.');
    [$l, $err] = up_releases(true); if ($err !== '') fail($err);
    $rel = null; foreach ($l as $r) if ($r['v'] === $want) $rel = $r;
    $cur = up_current();
    if (!$rel) fail('Ez a verzió nincs a kiadások között.');
    if (!version_compare($rel['v'], $cur, '>')) fail('Ez a verzió már telepítve van.');
    $url = $rel['zip'] !== '' ? $rel['zip'] : $rel['zipball'];
    if ($url === '') fail('A kiadáshoz nincs letölthető csomag.');
    $tmpd = rtrim($DIR, '/\\') . '/update-tmp'; up_rmtree($tmpd); @mkdir($tmpd, 0750, true);
    $zf = $tmpd . '/pkg.zip';
    $d = up_fetch($url, UP_MAX_ZIP, $zf);
    if ($d['err'] !== '' || $d['code'] !== 200) { up_rmtree($tmpd); fail('A letöltés nem sikerült: ' . ($d['err'] ?: 'HTTP ' . $d['code'])); }
    $sha = ''; $shaChecked = false;
    if ($rel['sha'] !== '') {
        $s = up_fetch($rel['sha'], 4096);
        if ($s['err'] === '' && $s['code'] === 200 && preg_match('/\b([a-f0-9]{64})\b/i', $s['body'], $m)) { $sha = strtolower($m[1]); }
        else { up_rmtree($tmpd); fail('A kiadás ellenőrző összeg fájlja nem olvasható.'); }
        if (!hash_equals($sha, hash_file('sha256', $zf))) { up_rmtree($tmpd); fail('Az ellenőrző összeg nem egyezik – a letöltés sérült vagy módosították. Semmi nem változott.'); }
        $shaChecked = true;
    }
    $z = new ZipArchive();
    if ($z->open($zf) !== true) { up_rmtree($tmpd); fail('A letöltött csomag nem érvényes zip fájl.'); }
    // find the application root inside the zip (the folder that holds index.html + api.php + VERSION)
    $names = []; for ($i = 0; $i < $z->numFiles; $i++) $names[] = $z->getNameIndex($i);
    if (count($names) > UP_MAX_FILES) { $z->close(); up_rmtree($tmpd); fail('A csomag túl sok fájlt tartalmaz.'); }
    $prefix = null;
    foreach ($names as $n) { if (preg_match('#^(.*?)api\.php$#', $n, $m) && in_array($m[1] . 'index.html', $names, true) && in_array($m[1] . 'VERSION', $names, true)) { if ($prefix === null || strlen($m[1]) < strlen($prefix)) $prefix = $m[1]; } }
    if ($prefix === null) { $z->close(); up_rmtree($tmpd); fail('A csomag nem GozieDarts kiadásnak látszik.'); }
    $files = []; $total = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        if (substr($n, -1) === '/' || strpos($n, $prefix) !== 0) continue;
        $rel2 = substr($n, strlen($prefix));
        if (in_array($rel2, UP_SKIP, true) || preg_match('#^darts-data#', $rel2)) continue;
        if (!up_safe_rel($rel2)) { $z->close(); up_rmtree($tmpd); fail('A csomag nem engedélyezett fájlt tartalmaz: ' . mb_substr($rel2, 0, 80)); }
        $st = $z->statIndex($i); $total += (int)$st['size'];
        if ($total > UP_MAX_UNPACKED) { $z->close(); up_rmtree($tmpd); fail('A kicsomagolt csomag túl nagy.'); }
        $files[$rel2] = $i;
    }
    $ver = trim((string)$z->getFromName($prefix . 'VERSION'));
    if ($ver !== $rel['v']) { $z->close(); up_rmtree($tmpd); fail('A csomag verziója (' . $ver . ') nem egyezik a kiadáséval (' . $rel['v'] . '). Semmi nem változott.'); }
    // read everything into memory, check PHP syntax
    $data = [];
    foreach ($files as $p => $i) {
        $c = $z->getFromIndex($i); if ($c === false) { $z->close(); up_rmtree($tmpd); fail('Egy fájl nem olvasható a csomagból: ' . $p); }
        if (strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'php') {
            try { token_get_all($c, TOKEN_PARSE); } catch (Throwable $e) { $z->close(); up_rmtree($tmpd); fail('Szintaktikai hiba itt: ' . $p . ' – a frissítés nem települt.'); }
        }
        $data[$p] = $c;
    }
    $z->close();
    foreach (['index.html', 'api.php', 'core.php', 'VERSION'] as $must) if (!isset($data[$must])) { up_rmtree($tmpd); fail('A csomag hiányos (' . $must . ' hiányzik).'); }
    // backup of the files that will be replaced
    $bk = rtrim($DIR, '/\\') . '/backup/' . $cur . '-' . date('Ymd-His');
    foreach ($data as $p => $c) {
        $dst = __DIR__ . '/' . $p;
        if (is_file($dst)) { $b = $bk . '/' . $p; @mkdir(dirname($b), 0750, true); if (!@copy($dst, $b)) { up_rmtree($tmpd); fail('A biztonsági mentés nem készíthető el (írható az adatmappa?). Semmi nem változott.'); } }
    }
    // write (temp file + rename); roll back on error
    $done = [];
    $rollback = function () use (&$done, $bk) {
        foreach ($done as $p => $existed) { $dst = __DIR__ . '/' . $p; if ($existed) @copy($bk . '/' . $p, $dst); else @unlink($dst); }
    };
    foreach ($data as $p => $c) {
        $dst = __DIR__ . '/' . $p; $existed = is_file($dst);
        if (!is_dir(dirname($dst)) && !@mkdir(dirname($dst), 0755, true)) { $rollback(); up_rmtree($tmpd); fail('Egy mappa nem hozható létre: ' . dirname($p) . '. A korábbi verzió visszaállt.'); }
        $t = $dst . '.upd-tmp';
        if (@file_put_contents($t, $c) !== strlen($c) || !@rename($t, $dst)) { @unlink($t); $rollback(); up_rmtree($tmpd); fail('Egy fájl nem írható: ' . $p . '. A korábbi verzió visszaállt.'); }
        $done[$p] = $existed;
        if (function_exists('opcache_invalidate') && substr($p, -4) === '.php') @opcache_invalidate($dst, true);
    }
    up_rmtree($tmpd);
    // keep only the last backups
    $all = glob(rtrim($DIR, '/\\') . '/backup/*', GLOB_ONLYDIR) ?: []; sort($all);
    while (count($all) > UP_KEEP_BACKUPS) up_rmtree(array_shift($all));
    out(['ok' => 1, 'cur' => $rel['v'], 'files' => count($data), 'sha' => $shaChecked ? 1 : 0, 'backup' => basename($bk)]);
}

function up_action($act, $in, $me) {
    if ($act === 'adm_upd_check') out(array_merge(up_status(!empty($in['force'])), ['pri' => mod_is_primary($me)]));
    if ($act === 'adm_upd_apply') {
        if (!mod_is_primary($me)) fail('Frissítést csak az alapadmin telepíthet.');
        up_apply($in);
    }
}
