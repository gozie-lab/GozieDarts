<?php
// GozieDarts installer wizard (Hungarian / English / German).
// After uploading, open it in the browser (e.g. https://your-site.example/install.php), fill in the form, done.
// It deletes itself when finished (if it cannot, delete it by hand).
define('CORE_SOFT', 1);
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/friends.php';
require_once __DIR__ . '/ranking.php';
require_once __DIR__ . '/challenges.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- language: ?lang= / POST / cookie / Accept-Language / default en ---
$L = [
  'hu' => [
    'title' => 'GozieDarts telepítés',
    'sub' => 'Telepítés',
    'already_t' => 'A telepítés már megtörtént.',
    'already_b' => 'Biztonsági okból töröld az <code>install.php</code> fájlt a tárhelyről. A klub adatait (név, szín, logó) a <code>darts-config.php</code> fájlban módosíthatod.',
    'open_app' => 'Az alkalmazás megnyitása',
    'hasdata_t' => 'Ezen a tárhelyen már van felhasználói adat, ezért a telepítő nem fut.',
    'hasdata_b' => 'Ha frissítés a cél, a fájlok felülírása elég, a telepítőre nincs szükség: töröld az <code>install.php</code> fájlt. Ha mégis újra szeretnél telepíteni, töröld előbb ezt az adatmappát (FTP-vel, a webgyökér fölött keresd):',
    'php_ver' => 'PHP verzió: %s%s',
    'php_need' => ' (legalább 7.4 kell)',
    'php_ext' => 'PHP bővítmény: %s',
    'sq_ok' => 'SQLite adatbázis elérhető',
    'sq_no' => 'SQLite nincs a tárhelyen – egyszerű fájlos tárolást használ (kisebb klubnak ez is elég)',
    'dir_out' => 'Adatmappa írható és a webgyökéren kívül van (ajánlott)',
    'dir_in' => 'Adatmappa írható (a program mappájában, védve – ha lehet, a webgyökéren kívülre érdemes tenni, lásd a leírást)',
    'dir_bad' => 'Az adatmappa nem írható',
    'dir_web' => 'Az adatok a webgyökérben vannak. Az <code>.htaccess</code> védi őket (Apache), de biztonságosabb a webgyökéren kívül: lásd a telepítési útmutatót (INSTALL)',
    'cfg_ok' => 'A beállítófájl létrehozható',
    'cfg_no' => 'A mappa nem írható – a beállításokat a végén kézzel kell majd bemásolnod',
    'e_club' => 'A klub neve nem használható.',
    'e_color' => 'Érvénytelen szín.',
    'e_light' => 'Ez a szín túl világos (a gombokon fehér a felirat). Válassz sötétebbet.',
    'e_name' => 'Az admin neve 3–20 karakter lehet (betű, szám, szóköz, . _ -).',
    'e_pw' => 'A jelszó legalább 6 karakter legyen.',
    'e_pw2' => 'A két jelszó nem egyezik.',
    'e_abs' => 'Az adatmappa teljes (abszolút) útvonalát add meg, pl. /home/felhasznalo/darts-adat',
    'e_write' => 'Ebbe a mappába a szerver nem tud írni (hozd létre, vagy adj írási jogot): %s',
    'e_db' => 'Az adatbázis nem hozható létre ebben a mappában.',
    'e_dbw' => 'Az adatbázis nem írható.',
    'e_taken' => 'Ez a név már foglalt.',
    'done' => '✔ Kész! A telepítés befejeződött.',
    'done_code' => 'Ez a <b>helyreállító kódod</b> – írd fel vagy mentsd el, ha elfelejted a jelszavad, ezzel tudsz újat beállítani. Később már nem látod:',
    'cfg_fail' => '<b>A beállítófájlt nem sikerült létrehozni.</b> Hozd létre kézzel <code>darts-config.php</code> néven a telepített mappában, ezzel a tartalommal:',
    'logo_fail' => 'A logót nem sikerült feltölteni: %s (az admin felületen később is megteheted.)',
    'deleted' => 'Az <code>install.php</code> törölte magát.',
    'notdel' => '<b>Töröld az <code>install.php</code> fájlt</b> a tárhelyről!',
    'admin_note' => 'Az admin felület: <a href="admin.html">admin.html</a> – itt kezelheted a játékosokat (hozzáadás, törlés, jelszó), a jelentéseket, a klub nevét és színét. Jelentkezz be az admin fiókoddal (%s).',
    'fatal' => '<b class="no">A telepítés nem folytatható.</b><p class="m">Javítsd a fenti ✖ jelölt pontokat (vagy kérd a tárhelyszolgáltató segítségét), majd frissítsd az oldalt.</p>',
    'l_club' => 'A klub neve (a nyitóképen, bal felül jelenik meg; elhagyható)',
    'ph_club' => 'pl. Budapesti Darts Klub',
    'l_color' => 'Főszín',
    'l_logo' => 'Logó (nem kötelező; png, jpg, gif vagy webp)',
    'l_reg' => 'Regisztráció',
    'r_open' => 'Nyitott – bárki létrehozhat fiókot',
    'r_closed' => 'Zárt – csak az admin hoz létre fiókokat (kis klubnak ajánlott)',
    'reg_later' => 'Később az admin felületen bármikor módosítható.',
    'l_dd' => 'Hol tárolódjanak az adatok (fiókok, meccsek)?',
    'dd_auto' => 'Automatikus (ajánlott)',
    'dd_o' => ' – <span class="ok">a webgyökéren kívül van ✔</span>',
    'dd_i' => ' – <span class="wn">a webgyökéren belül van (védett, de biztonságosabb kívül: add meg lent a saját útvonalat)</span>',
    'dd_custom' => 'Saját mappa (a webgyökéren kívül)',
    'ph_dd' => 'pl. /home/felhasznalo/darts-adat',
    'l_admin' => 'Admin neve',
    'l_pw' => 'Admin jelszava (legalább 6 karakter)',
    'l_pw2' => 'Jelszó még egyszer',
    'btn' => 'Telepítés',
    'f_note' => 'Az admin fiók kezeli a jelentéseket, és felfüggeszthet vagy törölhet játékosokat. A klub neve, színe és logója, valamint a további adminok később az admin felületen kezelhetők.',
  ],
  'en' => [
    'title' => 'GozieDarts installer',
    'sub' => 'Installation',
    'already_t' => 'Installation has already been completed.',
    'already_b' => 'For security reasons, delete <code>install.php</code> from your hosting. You can change the club details (name, colour, logo) in <code>darts-config.php</code>.',
    'open_app' => 'Open the app',
    'hasdata_t' => 'This hosting already contains user data, so the installer will not run.',
    'hasdata_b' => 'If you are updating, overwriting the files is enough and the installer is not needed: delete <code>install.php</code>. If you really want to reinstall, first delete this data folder (via FTP; look above the web root):',
    'php_ver' => 'PHP version: %s%s',
    'php_need' => ' (7.4 or newer is required)',
    'php_ext' => 'PHP extension: %s',
    'sq_ok' => 'SQLite database available',
    'sq_no' => 'SQLite is not available on this hosting – simple file storage will be used (enough for a small club)',
    'dir_out' => 'Data folder is writable and outside the web root (recommended)',
    'dir_in' => 'Data folder is writable (inside the app folder, protected – if possible, move it outside the web root, see the guide)',
    'dir_bad' => 'The data folder is not writable',
    'dir_web' => 'The data is inside the web root. <code>.htaccess</code> protects it (Apache), but it is safer outside the web root: see the installation guide (INSTALL)',
    'cfg_ok' => 'The configuration file can be created',
    'cfg_no' => 'The folder is not writable – you will have to copy the settings in by hand at the end',
    'e_club' => 'This club name cannot be used.',
    'e_color' => 'Invalid colour.',
    'e_light' => 'This colour is too light (button labels are white). Pick a darker one.',
    'e_name' => 'The admin name must be 3–20 characters (letters, digits, spaces, . _ -).',
    'e_pw' => 'The password must be at least 6 characters.',
    'e_pw2' => 'The two passwords do not match.',
    'e_abs' => 'Enter the full (absolute) path of the data folder, e.g. /home/user/darts-data',
    'e_write' => 'The server cannot write to this folder (create it or grant write permission): %s',
    'e_db' => 'The database cannot be created in this folder.',
    'e_dbw' => 'The database is not writable.',
    'e_taken' => 'This name is already taken.',
    'done' => '✔ Done! The installation is complete.',
    'done_code' => 'This is your <b>recovery code</b> – write it down or save it. If you forget your password, you can set a new one with it. You will not see it again:',
    'cfg_fail' => '<b>The configuration file could not be created.</b> Create it by hand as <code>darts-config.php</code> in the installation folder with this content:',
    'logo_fail' => 'The logo could not be uploaded: %s (you can do it later on the admin page.)',
    'deleted' => '<code>install.php</code> has deleted itself.',
    'notdel' => '<b>Delete <code>install.php</code></b> from your hosting!',
    'admin_note' => 'The admin page: <a href="admin.html">admin.html</a> – manage players (add, delete, password), reports, and the club name and colour there. Sign in with your admin account (%s).',
    'fatal' => '<b class="no">The installation cannot continue.</b><p class="m">Fix the points marked ✖ above (or ask your hosting provider for help), then reload the page.</p>',
    'l_club' => 'Club name (shown at the top left of the start screen; optional)',
    'ph_club' => 'e.g. Springfield Darts Club',
    'l_color' => 'Main colour',
    'l_logo' => 'Logo (optional; png, jpg, gif or webp)',
    'l_reg' => 'Registration',
    'r_open' => 'Open – anyone can create an account',
    'r_closed' => 'Closed – only the admin creates accounts (recommended for a small club)',
    'reg_later' => 'You can change this at any time later on the admin page.',
    'l_dd' => 'Where should the data (accounts, matches) be stored?',
    'dd_auto' => 'Automatic (recommended)',
    'dd_o' => ' – <span class="ok">outside the web root ✔</span>',
    'dd_i' => ' – <span class="wn">inside the web root (protected, but safer outside: enter your own path below)</span>',
    'dd_custom' => 'Custom folder (outside the web root)',
    'ph_dd' => 'e.g. /home/user/darts-data',
    'l_admin' => 'Admin name',
    'l_pw' => 'Admin password (at least 6 characters)',
    'l_pw2' => 'Repeat password',
    'btn' => 'Install',
    'f_note' => 'The admin account handles reports and can suspend or delete players. The club name, colour and logo, as well as additional admins, can be managed later on the admin page.',
  ],
  'de' => [
    'title' => 'GozieDarts-Installation',
    'sub' => 'Installation',
    'already_t' => 'Die Installation wurde bereits durchgeführt.',
    'already_b' => 'Lösche aus Sicherheitsgründen die Datei <code>install.php</code> vom Webspace. Die Vereinsdaten (Name, Farbe, Logo) kannst du in der Datei <code>darts-config.php</code> ändern.',
    'open_app' => 'App öffnen',
    'hasdata_t' => 'Auf diesem Webspace sind bereits Benutzerdaten vorhanden, daher läuft die Installation nicht.',
    'hasdata_b' => 'Wenn du nur aktualisieren willst, genügt es, die Dateien zu überschreiben; der Installer wird nicht benötigt: Lösche <code>install.php</code>. Wenn du wirklich neu installieren möchtest, lösche zuerst diesen Datenordner (per FTP; suche oberhalb des Web-Roots):',
    'php_ver' => 'PHP-Version: %s%s',
    'php_need' => ' (mindestens 7.4 erforderlich)',
    'php_ext' => 'PHP-Erweiterung: %s',
    'sq_ok' => 'SQLite-Datenbank verfügbar',
    'sq_no' => 'SQLite ist auf diesem Webspace nicht verfügbar – es wird einfache Dateispeicherung verwendet (für einen kleinen Verein genügt das)',
    'dir_out' => 'Datenordner ist beschreibbar und liegt außerhalb des Web-Roots (empfohlen)',
    'dir_in' => 'Datenordner ist beschreibbar (im Programmordner, geschützt – wenn möglich außerhalb des Web-Roots anlegen, siehe Anleitung)',
    'dir_bad' => 'Der Datenordner ist nicht beschreibbar',
    'dir_web' => 'Die Daten liegen im Web-Root. Die <code>.htaccess</code> schützt sie (Apache), außerhalb des Web-Roots ist es aber sicherer: siehe Installationsanleitung (INSTALL)',
    'cfg_ok' => 'Die Konfigurationsdatei kann erstellt werden',
    'cfg_no' => 'Der Ordner ist nicht beschreibbar – die Einstellungen musst du am Ende von Hand einfügen',
    'e_club' => 'Dieser Vereinsname kann nicht verwendet werden.',
    'e_color' => 'Ungültige Farbe.',
    'e_light' => 'Diese Farbe ist zu hell (die Beschriftung der Buttons ist weiß). Wähle eine dunklere.',
    'e_name' => 'Der Admin-Name muss 3–20 Zeichen lang sein (Buchstaben, Ziffern, Leerzeichen, . _ -).',
    'e_pw' => 'Das Passwort muss mindestens 6 Zeichen haben.',
    'e_pw2' => 'Die beiden Passwörter stimmen nicht überein.',
    'e_abs' => 'Gib den vollständigen (absoluten) Pfad des Datenordners an, z. B. /home/benutzer/darts-daten',
    'e_write' => 'Der Server kann in diesen Ordner nicht schreiben (lege ihn an oder vergib Schreibrechte): %s',
    'e_db' => 'In diesem Ordner kann die Datenbank nicht angelegt werden.',
    'e_dbw' => 'Die Datenbank ist nicht beschreibbar.',
    'e_taken' => 'Dieser Name ist bereits vergeben.',
    'done' => '✔ Fertig! Die Installation ist abgeschlossen.',
    'done_code' => 'Das ist dein <b>Wiederherstellungscode</b> – schreibe ihn auf oder speichere ihn. Wenn du dein Passwort vergisst, kannst du damit ein neues festlegen. Später wird er nicht mehr angezeigt:',
    'cfg_fail' => '<b>Die Konfigurationsdatei konnte nicht erstellt werden.</b> Lege sie von Hand als <code>darts-config.php</code> im Installationsordner mit diesem Inhalt an:',
    'logo_fail' => 'Das Logo konnte nicht hochgeladen werden: %s (du kannst es später auf der Admin-Seite nachholen.)',
    'deleted' => '<code>install.php</code> hat sich selbst gelöscht.',
    'notdel' => '<b>Lösche <code>install.php</code></b> vom Webspace!',
    'admin_note' => 'Die Admin-Seite: <a href="admin.html">admin.html</a> – hier verwaltest du Spieler (hinzufügen, löschen, Passwort), Meldungen sowie Name und Farbe des Vereins. Melde dich mit deinem Admin-Konto an (%s).',
    'fatal' => '<b class="no">Die Installation kann nicht fortgesetzt werden.</b><p class="m">Behebe die oben mit ✖ markierten Punkte (oder bitte deinen Hosting-Anbieter um Hilfe) und lade die Seite neu.</p>',
    'l_club' => 'Name des Vereins (wird links oben auf dem Startbildschirm angezeigt; optional)',
    'ph_club' => 'z. B. Dartverein Musterstadt',
    'l_color' => 'Hauptfarbe',
    'l_logo' => 'Logo (optional; png, jpg, gif oder webp)',
    'l_reg' => 'Registrierung',
    'r_open' => 'Offen – jeder kann ein Konto anlegen',
    'r_closed' => 'Geschlossen – nur der Admin legt Konten an (für kleine Vereine empfohlen)',
    'reg_later' => 'Kann später jederzeit auf der Admin-Seite geändert werden.',
    'l_dd' => 'Wo sollen die Daten (Konten, Spiele) gespeichert werden?',
    'dd_auto' => 'Automatisch (empfohlen)',
    'dd_o' => ' – <span class="ok">außerhalb des Web-Roots ✔</span>',
    'dd_i' => ' – <span class="wn">innerhalb des Web-Roots (geschützt, aber außerhalb sicherer: gib unten einen eigenen Pfad an)</span>',
    'dd_custom' => 'Eigener Ordner (außerhalb des Web-Roots)',
    'ph_dd' => 'z. B. /home/benutzer/darts-daten',
    'l_admin' => 'Admin-Name',
    'l_pw' => 'Admin-Passwort (mindestens 6 Zeichen)',
    'l_pw2' => 'Passwort wiederholen',
    'btn' => 'Installieren',
    'f_note' => 'Das Admin-Konto bearbeitet Meldungen und kann Spieler sperren oder löschen. Name, Farbe und Logo des Vereins sowie weitere Admins lassen sich später auf der Admin-Seite verwalten.',
  ],
];
$LANG = 'en';
foreach ([$_GET['lang'] ?? null, $_POST['lang'] ?? null, $_COOKIE['gd_lang'] ?? null] as $cand) { if (is_string($cand) && isset($L[$cand])) { $LANG = $cand; break; } $cand = null; }
if (!isset($cand) || $cand === null) {
    $al = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    foreach (['hu', 'de', 'en'] as $c) { if (preg_match('/(^|,)\s*' . $c . '/', $al)) { $LANG = $c; break; } }
}
if (isset($_GET['lang']) && is_string($_GET['lang']) && isset($L[$_GET['lang']])) @setcookie('gd_lang', $LANG, time() + 86400 * 30, '/');
function t($k, ...$a) { global $L, $LANG; $s = $L[$LANG][$k] ?? ($L['en'][$k] ?? $k); return $a ? vsprintf($s, $a) : $s; }
function page($body) {
    global $LANG;
    $sw = ''; foreach (['hu' => 'HU', 'en' => 'EN', 'de' => 'DE'] as $lc => $ln) $sw .= '<a href="?lang=' . $lc . '"' . ($lc === $LANG ? ' class="cur"' : '') . '>' . $ln . '</a>';
    echo '<!doctype html><html lang="' . $LANG . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h(t('title')) . '</title><style>
:root{--bg:#f3f4f6;--card:#fff;--tx:#111827;--mut:#6b7280;--bd:#d1d5db;--ac:#16a34a;--bad:#dc2626;--warn:#b45309}
@media(prefers-color-scheme:dark){:root{--bg:#0f1217;--card:#1a2029;--tx:#f3f4f6;--mut:#9ca3af;--bd:#2d3643;--warn:#f59e0b}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
.w{max-width:560px;margin:0 auto;padding:16px}h1{font-size:24px;margin:12px 0 4px}.c{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:16px;margin:12px 0}
label{display:block;margin:12px 0 4px;color:var(--mut);font-size:14px}input[type=text],input[type=password]{width:100%;padding:12px;border-radius:10px;border:1px solid var(--bd);background:var(--bg);color:var(--tx);font:inherit}
input[type=color]{width:64px;height:44px;padding:2px;border-radius:10px;border:1px solid var(--bd);background:var(--bg)}
button,.btn{display:inline-block;width:100%;margin-top:16px;padding:14px;border:0;border-radius:12px;background:var(--ac);color:#fff;font:600 18px system-ui;cursor:pointer;text-align:center;text-decoration:none}
.ok{color:var(--ac)}.no{color:var(--bad)}.wn{color:var(--warn)}.m{color:var(--mut);font-size:14px}ul{padding-left:0;list-style:none;margin:0}li{padding:4px 0}
.code{font:700 26px/1.3 ui-monospace,Menlo,Consolas,monospace;letter-spacing:1px;text-align:center;margin:14px 0;user-select:all}textarea{width:100%;height:150px;font:13px ui-monospace,monospace}.lg{text-align:right;font-size:14px}.lg a{color:var(--mut);text-decoration:none;padding:2px 6px;border-radius:6px}.lg a.cur{background:var(--ac);color:#fff}
</style></head><body><div class="w"><div class="lg">' . $sw . '</div><h1>🎯 GozieDarts</h1><div class="m">' . t('sub') . '</div>' . $body . '</div></body></html>';
    exit;
}
function row($ok, $text, $warn = false) { return '<li><span class="' . ($ok ? 'ok' : ($warn ? 'wn' : 'no')) . '">' . ($ok ? '✔' : ($warn ? '⚠' : '✖')) . '</span> ' . $text . '</li>'; }

// --- already installed, or data exists: the installer does not run (so nobody else can overwrite it) ---
if (!empty($CFG['installed'])) {
    http_response_code(403);
    page('<div class="c"><b>' . t('already_t') . '</b><p class="m">' . t('already_b') . '</p><a class="btn" href="./">' . t('open_app') . '</a></div>');
}
if ($ST && $ST->count('user') > 0) {
    http_response_code(403);
    page('<div class="c"><b>' . t('hasdata_t') . '</b><p class="m">' . t('hasdata_b') . '</p><p><code>' . h($DIR) . '</code></p></div>');
}

// --- environment checks ---
$checks = []; $fatal = false;
$phpok = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks[] = row($phpok, t('php_ver', h(PHP_VERSION), $phpok ? '' : t('php_need'))); if (!$phpok) $fatal = true;
foreach (['mbstring', 'json'] as $ext) { $o = extension_loaded($ext); $checks[] = row($o, t('php_ext', $ext)); if (!$o) $fatal = true; }
$sq = extension_loaded('pdo_sqlite');
$checks[] = row($sq, $sq ? t('sq_ok') : t('sq_no'), true);
$dirok = $CORE_ERR === '' && $ST;
$checks[] = row($dirok, $dirok ? ($DIR_OUTSIDE ? t('dir_out') : t('dir_in')) : h($CORE_ERR ?: t('dir_bad')), !$dirok && false);
if (!$dirok) $fatal = true;
if ($dirok && !$DIR_OUTSIDE) $checks[] = row(false, t('dir_web'), true);
$cfgw = is_writable(__DIR__);
$checks[] = row($cfgw, $cfgw ? t('cfg_ok') : t('cfg_no'), true);

// --- form handling ---
$err = ''; $v = ['club' => '', 'color' => '#16a34a', 'name' => '', 'dd' => 'auto', 'ddp' => '', 'reg' => 1];
$AUTO_DIR = $DIR; $AUTO_OUT = $DIR_OUTSIDE;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$fatal) {
    $v['club'] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['club'] ?? ''))), 0, 40);
    $v['color'] = (string)($_POST['color'] ?? '#16a34a');
    $v['name'] = ac_clean_name($_POST['name'] ?? '');
    $v['reg'] = ($_POST['reg'] ?? 'open') === 'closed' ? 0 : 1;
    $v['dd'] = ($_POST['dd'] ?? 'auto') === 'custom' ? 'custom' : 'auto'; $v['ddp'] = rtrim(trim((string)($_POST['ddp'] ?? '')), '/\\');
    $pw = (string)($_POST['pw'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
    $lum = 0;
    if (preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $v['color'], $m)) $lum = (0.2126 * hexdec($m[1]) + 0.7152 * hexdec($m[2]) + 0.0722 * hexdec($m[3])) / 255;
    if ($v['club'] !== '' && pf_bad($v['club'])) $err = t('e_club');
    elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $v['color'])) $err = t('e_color');
    elseif ($lum > 0.62) $err = t('e_light');
    elseif (!ac_name_ok($v['name'])) $err = t('e_name');
    elseif (mb_strlen($pw) < 6 || mb_strlen($pw) > 100) $err = t('e_pw');
    elseif ($pw !== $pw2) $err = t('e_pw2');
    if ($err === '' && $v['dd'] === 'custom') {
        $d = $v['ddp'];
        if ($d === '' || !preg_match('#^(/|[A-Za-z]:[\\/])#', $d)) $err = t('e_abs');
        elseif (!data_dir_prepare($d) || !is_writable($d)) $err = t('e_write', h($d));
        else {
            $DIR = $d; $CFG['data_dir'] = $d;
            try { $ST = st_init($DIR, $CFG); } catch (Throwable $e) { $err = t('e_db'); }
        }
    }
    if ($err === '') {
        [$fp, $ex] = $ST->lock('user', st_user_key($v['name']), true);
        if (!$fp) $err = t('e_dbw');
        elseif ($ex !== null) { $ST->release($fp); $err = t('e_taken'); }
        else {
            [$u, $code] = ac_create($v['name'], $pw);
            $u['tokens'] = [];
            $ST->save($fp, $u);
            $ST->commit();
            $logoWarn = ''; $logo = '';
            if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
                [$fn, $le] = logo_save((string)file_get_contents($_FILES['logo']['tmp_name']));
                if ($fn) $logo = $fn; else $logoWarn = $le;
            }
            $conf = ['installed' => time(), 'club' => $v['club'], 'color' => strtolower($v['color']), 'logo' => $logo, 'admins' => [$v['name']], 'storage' => 'auto', 'reg' => (bool)$v['reg']];
            if ($v['dd'] === 'custom') $conf['data_dir'] = $v['ddp'];
            $php = "<?php\n// GozieDarts configuration (created by the installer). You may edit it; the 'data_dir' and 'logo' lines are optional.\nreturn " . var_export($conf, true) . ";\n";
            $cf = __DIR__ . '/darts-config.php';
            $saved = @file_put_contents($cf, $php) !== false;
            if ($saved) @chmod($cf, 0640);
            // name and colour of the installed app (PWA manifest)
            $mf = __DIR__ . '/manifest.webmanifest';
            if (is_file($mf)) {
                $mj = json_decode((string)@file_get_contents($mf), true);
                if (is_array($mj)) { $nm = $v['club'] !== '' ? $v['club'] : 'GozieDarts'; $mj['name'] = $nm; $mj['short_name'] = mb_substr($nm, 0, 12); $mj['theme_color'] = strtolower($v['color']); @file_put_contents($mf, json_encode($mj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)); }
            }
            $deleted = $saved && @unlink(__FILE__);
            $body = '<div class="c"><b class="ok">' . t('done') . '</b>'
                . '<p>' . t('done_code') . '</p><div class="code">' . h($code) . '</div>'
                . ($saved ? '' : '<p class="no">' . t('cfg_fail') . '</p><textarea readonly>' . h($php) . '</textarea>')
                . ($logoWarn ? '<p class="wn">' . t('logo_fail', h($logoWarn)) . '</p>' : '')
                . ($deleted ? '<p class="ok">' . t('deleted') . '</p>' : '<p class="wn">' . t('notdel') . '</p>')
                . '<a class="btn" href="./">' . t('open_app') . '</a><p class="m">' . t('admin_note', h($v['name'])) . '</p></div>';
            page($body);
        }
    }
}

$form = '<div class="c"><ul>' . implode('', $checks) . '</ul></div>';
if ($fatal) page($form . '<div class="c">' . t('fatal') . '</div>');
page($form . '<form method="post" class="c" autocomplete="off" enctype="multipart/form-data"><input type="hidden" name="lang" value="' . $LANG . '">'
    . ($err ? '<p class="no"><b>' . h($err) . '</b></p>' : '')
    . '<label>' . t('l_club') . '</label><input type="text" name="club" maxlength="40" value="' . h($v['club']) . '" placeholder="' . h(t('ph_club')) . '">'
    . '<label>' . t('l_color') . '</label><input type="color" name="color" value="' . h($v['color']) . '">'
    . '<label>' . t('l_logo') . '</label><input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">'
    . '<label>' . t('l_reg') . '</label>'
    . '<label style="color:var(--tx);margin:4px 0"><input type="radio" name="reg" value="open"' . ($v['reg'] ? ' checked' : '') . '> ' . t('r_open') . '</label>'
    . '<label style="color:var(--tx);margin:4px 0"><input type="radio" name="reg" value="closed"' . ($v['reg'] ? '' : ' checked') . '> ' . t('r_closed') . '</label>'
    . '<div class="m" style="margin:0 0 8px 24px">' . t('reg_later') . '</div>'
    . '<label>' . t('l_dd') . '</label>'
    . '<label style="color:var(--tx);margin:4px 0"><input type="radio" name="dd" value="auto"' . ($v['dd'] === 'auto' ? ' checked' : '') . '> ' . t('dd_auto') . '</label>'
    . '<div class="m" style="margin:0 0 8px 24px">' . h($AUTO_DIR) . ($AUTO_OUT ? t('dd_o') : t('dd_i')) . '</div>'
    . '<label style="color:var(--tx);margin:4px 0"><input type="radio" name="dd" value="custom"' . ($v['dd'] === 'custom' ? ' checked' : '') . '> ' . t('dd_custom') . '</label>'
    . '<input type="text" name="ddp" value="' . h($v['ddp']) . '" placeholder="' . h(t('ph_dd')) . '" style="margin-left:0">'
    . '<label>' . t('l_admin') . '</label><input type="text" name="name" maxlength="20" value="' . h($v['name']) . '" autocomplete="username">'
    . '<label>' . t('l_pw') . '</label><input type="password" name="pw" autocomplete="new-password">'
    . '<label>' . t('l_pw2') . '</label><input type="password" name="pw2" autocomplete="new-password">'
    . '<button type="submit">' . t('btn') . '</button><p class="m">' . t('f_note') . '</p></form>');
