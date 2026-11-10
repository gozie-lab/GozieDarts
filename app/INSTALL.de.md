# GozieDarts – Installationsanleitung

🌐 [English](INSTALL.md) · [Magyar](INSTALL.hu.md) · **Deutsch**

Eine Installation = ein Verein. Du musst nicht programmieren können – du brauchst nur Webspace, auf dem PHP 7.4 oder
neuer läuft (fast jedes günstige Hosting-Paket bietet das).

## 1. Hochladen

Entpacke die Release-ZIP-Datei (oder kopiere den Ordner `app/` des Repositorys) und lade die Dateien per FTP oder
über den Dateimanager deines Webspaces in einen Ordner hoch, z. B. `darts`. Die Seite muss über **HTTPS**
(`https://...`) erreichbar sein.

## 2. Installer öffnen

Öffne im Browser `https://deine-seite.example/darts/install.php` (oben rechts kannst du die Sprache wechseln:
Ungarisch / Englisch / Deutsch). Grüne Haken bedeuten, dass alles in Ordnung ist. Bei einem roten ✖ hilft der Support
deines Hosting-Anbieters (nenne ihm den roten Punkt).

## 3. Formular ausfüllen

- **Name des Vereins** – wird links oben auf dem Startbildschirm angezeigt (optional).
- **Hauptfarbe** – Farbe der Buttons und Hervorhebungen (eine dunklere Farbe wird empfohlen, die Beschriftung ist weiß).
- **Logo** – optional (png, jpg, gif oder webp); später auch auf der Admin-Seite änderbar.
- **Registrierung** – offen (jeder kann ein Konto anlegen) oder geschlossen (nur der Admin legt Konten an).
- **Datenordner** – „Automatisch“ passt, wenn der Installer ihn als außerhalb des Web-Roots anzeigt (✔). Sonst gib den
  vollständigen Pfad eines eigenen Ordners **außerhalb** des Web-Roots an (z. B. `/home/benutzer/darts-daten`).
- **Admin-Name und -Passwort** – mit diesem Konto verwaltest du Meldungen und kannst Spieler sperren oder löschen.
  Merke es dir gut!

Klicke auf **Installieren**.

## 4. Wiederherstellungscode

Am Ende erhältst du einen Code (z. B. `ABCD-EFGH-JKMN`). Schreibe ihn auf – wenn du das Admin-Passwort vergisst,
kannst du damit ein neues festlegen. Er wird später nicht mehr angezeigt.

## 5. Fertig

`install.php` löscht sich selbst. Falls die Datei noch da ist, lösche sie von Hand. Öffne
`https://deine-seite.example/darts/` – der Vereinsname erscheint links oben. Auf dem Smartphone lässt sich die App
über das Browsermenü („Zum Startbildschirm hinzufügen“ / „App installieren“) installieren.

## Admin-Seite

`https://deine-seite.example/darts/admin.html` – Anmeldung mit dem Admin-Konto. Hier kannst du:

- Online-Konten hinzufügen, löschen, sperren und neue Passwörter setzen (Spieler, die ihr Passwort vergessen haben,
  erscheinen als rote Karte),
- weitere Admins ernennen (das kann nur der Basis-Admin – der im Installer angegebene),
- Meldungen bearbeiten,
- Name, Hauptfarbe und Logo des Vereins ändern.

## Lokale Spieler und Online-Konten

- **Online-Konto:** Name + Passwort; die Ergebnisse sind auch auf anderen Geräten verfügbar; diese Spieler erscheinen
  auf der Admin-Seite.
- **Lokaler Spieler:** nur ein im Browser des Geräts gespeicherter Name, ohne Konto. Auf der Seite *Spieler* der App
  steht bei jedem Eintrag, ob es ein Online-Konto (verknüpft oder nicht) oder nur lokal ist.

## Vereinsdaten von Hand (optional – die Admin-Seite überschreibt das)

Öffne `darts-config.php` in einem Texteditor:

```php
'club'  => 'Vereinsname',
'color' => '#16a34a',     // eine dunklere Farbe wird empfohlen (weiße Beschriftung)
'logo'  => 'logo.png',    // optional; Dateiname eines hochgeladenen Bildes oder eine https-Adresse
```

Änderungen gelten innerhalb weniger Sekunden. Alle Optionen siehe `darts-config.example.php`.

## Mehrere Vereine auf einem Server

Gib jedem Verein einen eigenen Ordner bzw. eine eigene Webadresse, lade die Dateien jeweils separat hoch und führe in
jedem `install.php` aus. Die Daten vermischen sich nicht (jede Installation bekommt einen eigenen Datenordner).
Wenn du `data_dir` von Hand setzt, verwende für jeden Verein einen **anderen** Pfad!

## Sicherheitsempfehlung

Standardmäßig landen die Daten (Konten, Spiele) im geschützten Ordner `darts-data…`. Wenn dein Webspace einen Ordner
außerhalb des Web-Roots (`public_html`) bietet, solltest du die Daten dort ablegen:

```php
'data_dir' => '/vollstaendiger/pfad/darts-daten',
```

in der `darts-config.php` – aber **vor** der ersten Installation oder indem du die Daten kopierst.

## Aktualisierung (neue Version)

**Über die Admin-Seite (empfohlen):** `admin.html` → Tab **Update**. Gibt es ein neueres GitHub-Release, werden die
Änderungshinweise angezeigt und der Basis-Admin kann mit *Installieren* aktualisieren. Voraussetzungen: PHP-Erweiterung
`zip`, ausgehendes HTTPS, beschreibbare App-Dateien. Ersetzte Dateien werden in `<Datenordner>/backup` gesichert (die
letzten 3 bleiben), bei Fehlern wird automatisch zurückgesetzt. `darts-config.php`, `install.php` und die Daten bleiben unberührt.
Optionen in `darts-config.php`: `'update_repo' => 'gozie-lab/GozieDarts'`, `'update' => false` (abschalten).

**Von Hand:** neue Dateien über die alten hochladen, aber **`darts-config.php` und den Ordner `darts-data` nicht
überschreiben** (das Release-Zip enthält sie nicht). `install.php` nicht erneut hochladen.
Installierte Apps (PWA) bemerken die neue Version und zeigen einen Hinweis „Update verfügbar".

## Sicherung

Lade den Ordner `darts-data` ab und zu (per FTP) herunter – er enthält alle Daten.

## Datenschutz

Die App enthält keine Werbung und kein Tracking. Ein Online-Konto braucht nur Name und Passwort (gehasht gespeichert).
Für die Daten ist der Vereinsbetreiber (Sie) verantwortlich. Alle Schriften sind enthalten – die App stellt keine
Anfragen an Dritte (die einzige ausgehende Anfrage des Servers ist die optionale Update-Prüfung bei GitHub; abschaltbar mit `'update' => false`).
