# 🎯 GozieDarts

🌐 [English](README.md) · [Magyar](README.hu.md) · **Deutsch**

Dart-Zähl-App für Vereine und Freunde – eine Single-Page-Web-App / PWA mit einem kleinen PHP-Server für Online-Spiele.
Keine Werbung, kein Tracking, **eine Installation = ein Verein**. Die Oberfläche gibt es auf Ungarisch, Englisch und Deutsch.

## Funktionen

- **X01** (301/501/701…, Double-in / Double-out / Master, Legs und Sets, Handicap, Bots) und **Cricket**
- Schnelle Punkteingabe, Checkout-Vorschläge, Korrektur früherer Aufnahmen (✏️), Rückgängig
- Statistiken pro Spiel und Spieler: Average, 100+/140+/180, höchster Checkout, Verlauf je Leg
- Spielverlauf, Spieler und Profile, **Training** und **Turniere**
- **Online-1-gegen-1-Spiele** mit Konten, Freunden und Herausforderungen
- Als App installierbar (PWA), lokale Spiele funktionieren offline; Schriften sind enthalten (keine externen Anfragen)
- **Admin-Seite** und **Update von GitHub** (siehe unten)

## Screenshots

<table>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/app-01-home.png" width="230"><br><sub>Startseite</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-02-new-game-players.png" width="230"><br><sub>Neues Spiel – Spieler</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-03-new-game-rules.png" width="230"><br><sub>Neues Spiel – Regeln</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/app-04-game.png" width="230"><br><sub>Punkteeingabe</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-05-game-menu.png" width="230"><br><sub>Spielmenü</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-06-correct-turns.png" width="230"><br><sub>Aufnahmen korrigieren</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/app-07-match-stats.png" width="230"><br><sub>Spielstatistik</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-08-cricket.png" width="230"><br><sub>Cricket</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-16-match-result.png" width="230"><br><sub>Spielergebnis</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/app-09-history.png" width="230"><br><sub>Verlauf</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-10-players.png" width="230"><br><sub>Spieler</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-10b-profile.png" width="230"><br><sub>Spielerprofil</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/app-11-training.png" width="230"><br><sub>Training</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-12-tournament.png" width="230"><br><sub>Turnier</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-13-online.png" width="230"><br><sub>Online-Spiel</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/app-14-account.png" width="230"><br><sub>Konto</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/app-15-settings.png" width="230"><br><sub>Einstellungen</sub></td>
</tr>
</table>

## Admin-Seite

`https://ihre-seite.example/darts/admin.html` – Anmeldung mit dem im Installer angelegten Admin-Konto. Die Admin-Seite gibt es auf Ungarisch, Englisch und Deutsch (Sprachauswahl oben).

#### Anmeldung
Nur Admin-Konten (der Basis-Admin aus dem Installer und von ihm ernannte Admins).

<img src="docs/screenshots/admin-1-login.png" width="640">

#### Spieler
Alle Online-Konten mit Suche; Spieler, die ihr Passwort vergessen haben, erscheinen als rote Karte, damit ein neues vergeben werden kann.

<img src="docs/screenshots/admin-2-players.png" width="640">

#### Spieleraktionen
Sperren, Löschen, neues Passwort, Admin ernennen/entziehen (Admins ernennen kann nur der Basis-Admin).

<img src="docs/screenshots/admin-3-player-actions.png" width="640">

#### Meldungen
Von Spielern gemeldete Inhalte; abschließen oder eingreifen.

<img src="docs/screenshots/admin-4-reports.png" width="640">

#### Verein
Vereinsname, Hauptfarbe und Logo – sofort wirksam, ohne Dateien zu bearbeiten.

<img src="docs/screenshots/admin-5-club.png" width="640">

#### Update
Zeigt die installierte und die neueste GitHub-Version mit Änderungshinweisen; der Basis-Admin installiert sie mit einem Klick.

<img src="docs/screenshots/admin-6-update.png" width="640">

## Update über die Admin-Seite

- Der Tab **Update** prüft das neueste [GitHub-Release](../../releases) des eingestellten Repositorys (standardmäßig `gozie-lab/GozieDarts`).
- Gibt es eine neuere Version, werden die **Änderungshinweise** angezeigt. *Installieren* darf **nur der Basis-Admin**.
- Das Release muss das Asset `GozieDarts.zip` enthalten (optional `GozieDarts.zip.sha256`). Geprüft werden Aufbau, erlaubte Dateitypen, Pfadtricks, PHP-Syntax, Version = Tag; die Dateien werden im Datenordner **gesichert** und atomar ersetzt, bei Fehlern **automatisch zurückgesetzt**.
- `darts-config.php`, `install.php` und der Datenordner bleiben unberührt. Voraussetzungen: PHP-Erweiterung `zip`, ausgehendes HTTPS, beschreibbare Dateien. Sonst manuell aktualisieren (siehe [INSTALL.de.md](app/INSTALL.de.md)).
- Optionen in `darts-config.php`: `update_repo`, `update` (`false` schaltet die Funktion ab).

## Installation

Inhalt von [`app/`](app/) (oder das Release-Zip) auf ein beliebiges PHP-7.4+-Hosting (HTTPS) hochladen, `install.php` öffnen und dem Assistenten folgen (Ungarisch / Englisch / Deutsch). Details: **[INSTALL.de.md](app/INSTALL.de.md)** · [English](app/INSTALL.md) · [Magyar](app/INSTALL.hu.md).

## Release erstellen

1. `app/VERSION`, `APP_VER` in `app/index.html` und den Cache-Namen `darts-vNN` in `app/sw.js` erhöhen.
2. Abschnitt in [CHANGELOG.md](CHANGELOG.md) ergänzen.
3. `git tag vX.Y && git push --tags` – der [Workflow](.github/workflows/release.yml) baut `GozieDarts.zip` und veröffentlicht das Release mit dem Changelog als Text.

## Schriften

*Inter* liegt in `app/fonts/` (SIL OFL); es gibt keine externen Schriftanfragen.

## Aufbau

| Pfad | Inhalt |
|---|---|
| `app/index.html` | die gesamte Client-App (eine Datei) |
| `app/*.php` | Server (`api.php` + Module), `install.php`-Assistent, `updater.php` |
| `app/admin.html` | Admin-Seite |
| `app/sw.js`, `app/manifest.webmanifest` | PWA |
| `app/fonts/` | mitgelieferte Schriften |
| `docs/screenshots/` | Screenshots |

## Lizenz

Gemeinfrei – siehe [LICENSE](LICENSE) ([Unlicense](https://unlicense.org)). Schriften: SIL Open Font License.
