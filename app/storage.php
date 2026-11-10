<?php
// Gozie Darts – tárolás. Egységes felület (gyűjtemény + kulcs -> JSON dokumentum), két háttérrel:
//  - SQLite (alapértelmezett, ha a tárhelyen van pdo_sqlite): egyetlen adatbázisfájl az adatmappában (a webgyökéren kívül),
//    kéréssorrendben zárolt (BEGIN IMMEDIATE) tranzakcióban, így a párhuzamos kérések nem tudják összekuszálni az adatot;
//  - JSON-fájlok (tartalék): a korábbi működés, fájlonkénti zárolással.
// Gyűjtemények: 'room' (szobák), 'user' (fiókok, kulcs: sha1(név)), 'doc' (egyedi dokumentumok: ranking, challenges, msgs, reports, presence).
// Beállítás a darts-config.php-ban: 'storage' => 'auto' | 'sqlite' | 'files'.

function st_user_key($name) { return sha1(mb_strtolower(trim($name))); }
function st_room_key($id) { return preg_replace('/[^a-f0-9]/', '', (string)$id); }

class StFiles {
    private $dir;
    function __construct($dir) { $this->dir = $dir; if (!is_dir("$dir/users")) @mkdir("$dir/users", 0750, true); }
    function kind() { return 'files'; }
    private function path($c, $k) {
        if ($c === 'room') return $this->dir . '/r_' . $k . '.json';
        if ($c === 'user') return $this->dir . '/users/u_' . $k . '.json';
        return $this->dir . '/' . preg_replace('/[^a-z0-9_]/', '', $k) . '.json';
    }
    function has($c, $k) { return is_file($this->path($c, $k)); }
    function get($c, $k) {
        $p = $this->path($c, $k);
        if (!is_file($p)) return null;
        $fp = @fopen($p, 'r'); if (!$fp) return null;
        flock($fp, LOCK_SH);   // írás közben ne olvassunk félkész fájlt
        $raw = stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp);
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }
    // [handle, adat|null]; ha nincs ilyen dokumentum és !$create, akkor [null, null]
    function lock($c, $k, $create = false) {
        $p = $this->path($c, $k);
        if (!$create && !is_file($p)) return [null, null];
        $fp = @fopen($p, 'c+'); if (!$fp) return [null, null];
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $d = $raw === '' ? null : json_decode($raw, true);
        if ($raw !== '' && !is_array($d)) { if (!$create) { flock($fp, LOCK_UN); fclose($fp); return [null, null]; } $d = null; }
        return [['f', $fp, $p], $d];
    }
    function save($h, $data) {
        $fp = $h[1];
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    }
    function release($h) {
        $fp = $h[1];
        if (fstat($fp)['size'] === 0) { flock($fp, LOCK_UN); fclose($fp); @unlink($h[2]); return; }   // üresen létrehozott fájl
        flock($fp, LOCK_UN); fclose($fp);
    }
    function put($c, $k, $data) { file_put_contents($this->path($c, $k), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX); }
    function del($c, $k) { @unlink($this->path($c, $k)); }
    function keys($c) {
        $res = [];
        if ($c === 'room') foreach (glob($this->dir . '/r_*.json') ?: [] as $f) $res[] = substr(basename($f), 2, -5);
        elseif ($c === 'user') foreach (glob($this->dir . '/users/u_*.json') ?: [] as $f) $res[] = substr(basename($f), 2, -5);
        return $res;
    }
    function all($c) { $r = []; foreach ($this->keys($c) as $k) { $d = $this->get($c, $k); if ($d !== null) $r[$k] = $d; } return $r; }
    function count($c) { return count($this->keys($c)); }
    function commit() {}
}

class StSqlite {
    private $db, $q = [];
    function __construct($file) {
        $new = !is_file($file);
        $this->db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
        $this->db->exec('PRAGMA busy_timeout=10000');
        try { $this->db->exec('PRAGMA journal_mode=WAL'); } catch (Throwable $e) {}
        $this->db->exec('PRAGMA synchronous=NORMAL');
        if ($new) @chmod($file, 0640);
        // a kérés egésze egy tranzakció: a párhuzamos kérések egymás után hajtódnak végre (a zárolást az SQLite adja)
        $this->db->exec('BEGIN IMMEDIATE');
        $this->db->exec('CREATE TABLE IF NOT EXISTS docs (c TEXT NOT NULL, k TEXT NOT NULL, v TEXT NOT NULL, PRIMARY KEY (c, k)) WITHOUT ROWID');
        $this->db->exec('CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)');
    }
    function kind() { return 'sqlite'; }
    private function st($sql) { return $this->q[$sql] ?? ($this->q[$sql] = $this->db->prepare($sql)); }
    function has($c, $k) { $s = $this->st('SELECT 1 FROM docs WHERE c=? AND k=?'); $s->execute([$c, $k]); return (bool)$s->fetchColumn(); }
    function get($c, $k) {
        $s = $this->st('SELECT v FROM docs WHERE c=? AND k=?'); $s->execute([$c, $k]);
        $v = $s->fetchColumn(); if ($v === false) return null;
        $d = json_decode($v, true); return is_array($d) ? $d : null;
    }
    function lock($c, $k, $create = false) {
        $d = $this->get($c, $k);
        if ($d === null && !$create) return [null, null];
        return [['q', $c, $k], $d];
    }
    function save($h, $data) { $this->put($h[1], $h[2], $data); }
    function release($h) {}
    function put($c, $k, $data) { $s = $this->st('INSERT OR REPLACE INTO docs (c,k,v) VALUES (?,?,?)'); $s->execute([$c, $k, json_encode($data, JSON_UNESCAPED_UNICODE)]); }
    function del($c, $k) { $s = $this->st('DELETE FROM docs WHERE c=? AND k=?'); $s->execute([$c, $k]); }
    function keys($c) { $s = $this->st('SELECT k FROM docs WHERE c=?'); $s->execute([$c]); return $s->fetchAll(PDO::FETCH_COLUMN); }
    function all($c) {
        $s = $this->st('SELECT k,v FROM docs WHERE c=?'); $s->execute([$c]); $r = [];
        foreach ($s->fetchAll(PDO::FETCH_NUM) as [$k, $v]) { $d = json_decode($v, true); if (is_array($d)) $r[$k] = $d; }
        return $r;
    }
    function count($c) { $s = $this->st('SELECT COUNT(*) FROM docs WHERE c=?'); $s->execute([$c]); return (int)$s->fetchColumn(); }
    function metaGet($k) { $s = $this->st('SELECT v FROM meta WHERE k=?'); $s->execute([$k]); $v = $s->fetchColumn(); return $v === false ? null : $v; }
    function metaSet($k, $v) { $s = $this->st('INSERT OR REPLACE INTO meta (k,v) VALUES (?,?)'); $s->execute([$k, $v]); }
    function commit() { try { $this->db->exec('COMMIT'); } catch (Throwable $e) {} }
    function commitStrict() { $this->db->exec('COMMIT'); }
    function begin() { $this->db->exec('BEGIN IMMEDIATE'); }
    function rollback() { try { $this->db->exec('ROLLBACK'); } catch (Throwable $e) {} }
}

// a korábbi JSON-fájlok egyszeri átemelése az SQLite-ba (siker után a fájlok törlődnek)
function st_import_files($st, $dir) {
    if ($st->metaGet('imported')) return;
    $files = []; $n = 0;
    foreach (glob($dir . '/r_*.json') ?: [] as $f) $files[] = ['room', substr(basename($f), 2, -5), $f];
    foreach (glob($dir . '/users/u_*.json') ?: [] as $f) $files[] = ['user', substr(basename($f), 2, -5), $f];
    foreach (['ranking', 'challenges', 'msgs', 'reports'] as $k) if (is_file("$dir/$k.json")) $files[] = ['doc', $k, "$dir/$k.json"];
    foreach ($files as [$c, $k, $f]) {
        $d = json_decode(@file_get_contents($f), true);
        if (is_array($d)) { $st->put($c, $k, $d); $n++; }
    }
    $st->metaSet('imported', date('c') . ' ' . $n);
    $st->commitStrict(); $st->begin();          // az átemelés végleges (hiba esetén kivétel: a fájlok nem törlődnek), új tranzakció indul
    foreach ($files as [$c, $k, $f]) if ($st->has($c, $k)) @unlink($f);
    foreach (['presence.json'] as $f) @unlink("$dir/$f");
    @rmdir($dir . '/users');
}

function st_init($dir, $cfg) {
    $want = $cfg['storage'] ?? 'auto';
    if ($want !== 'files' && extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        try {
            $st = new StSqlite($dir . '/darts.sqlite');
            st_import_files($st, $dir);
            register_shutdown_function(function () use ($st) {
                $e = error_get_last();
                if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) $st->rollback(); else $st->commit();
            });
            return $st;
        } catch (Throwable $e) { if ($want === 'sqlite') throw $e; }
    }
    return new StFiles($dir);
}
