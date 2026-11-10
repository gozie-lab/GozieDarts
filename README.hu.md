# 🎯 GozieDarts

🌐 [English](README.md) · **Magyar** · [Deutsch](README.de.md)

Darts pontozó alkalmazás klubok és barátok számára – egyoldalas webapp / PWA, online játékhoz egy kis PHP szerverrel.
Nincs reklám, nincs követés, **egy telepítés = egy klub**. A felület magyar, angol és német nyelvű.

🤖 A [Claude](https://claude.ai) (Anthropic) segítségével készült.

## Funkciók

- **X01** (301/501/701…, beszálló/kiszálló/master, legek és szettek, hendikep, botok) és **Cricket**
- Gyors pontbevitel, kiszálló-javaslat, korábbi körök javítása (✏️), visszavonás
- Statisztika meccsenként és játékosonként: átlag, 100+/140+/180, legnagyobb kiszálló, legenkénti alakulás
- Meccselőzmények, játékosok és profilok, **edzés** (Around the Clock, Bob's 27, Shanghai, kiszálló-gyakorló) és **versenyek**
- **Online 1v1 játék** fiókkal, barátokkal és kihívásokkal
- Telepíthető appként (PWA), helyi játék offline is működik; a betűtípusok a csomagban vannak (nincs külső kérés)
- **Admin felület** és **frissítés GitHubról** (lásd lent)

## Képernyőfotók

<table>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-01-home.png" width="230"><br><sub>Főoldal</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-02-new-game-players.png" width="230"><br><sub>Új játék – játékosok</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-03-new-game-rules.png" width="230"><br><sub>Új játék – szabályok</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-04-game.png" width="230"><br><sub>Pontozás</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-05-game-menu.png" width="230"><br><sub>Játék menü</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-06-correct-turns.png" width="230"><br><sub>Körök javítása</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-07-match-stats.png" width="230"><br><sub>Meccs statisztika</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-08-cricket.png" width="230"><br><sub>Cricket</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-16-match-result.png" width="230"><br><sub>Meccs eredmény</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-09-history.png" width="230"><br><sub>Előzmények</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-10-players.png" width="230"><br><sub>Játékosok</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-10b-profile.png" width="230"><br><sub>Játékos profil</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-11-training.png" width="230"><br><sub>Edzés</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-11c-shanghai.png" width="230"><br><sub>Shanghai edzés</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-12-tournament.png" width="230"><br><sub>Verseny</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-13-online.png" width="230"><br><sub>Online játék</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-14-account.png" width="230"><br><sub>Fiók</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/hu/app-15-settings.png" width="230"><br><sub>Beállítások</sub></td>
</tr>
</table>

## Admin felület

`https://az-oldalad.example/darts/admin.html` – a telepítőben megadott admin fiókkal lehet belépni. Az admin felület magyar, angol és német nyelvű (nyelvválasztó felül).

#### Belépés
Csak admin fiókkal (a telepítőben megadott alapadmin és az általa kinevezett adminok).

<img src="docs/screenshots/hu/admin-1-login.png" width="640">

#### Játékosok
Az összes online fiók kereséssel; az elfelejtett jelszavúak piros kártyán jelennek meg, hogy új jelszót lehessen adni.

<img src="docs/screenshots/hu/admin-2-players.png" width="640">

#### Játékos műveletek
Felfüggesztés, törlés, új jelszó, admin kinevezése / visszavonása (adminokat csak az alapadmin nevezhet ki).

<img src="docs/screenshots/hu/admin-3-player-actions.png" width="640">

#### Bejelentések
A játékosok bejelentései a bejelentett tartalommal; lezárható vagy intézkedhető.

<img src="docs/screenshots/hu/admin-4-reports.png" width="640">

#### Klub
Klub neve, főszíne és logója – azonnal érvényesül, fájlszerkesztés nélkül.

<img src="docs/screenshots/hu/admin-5-club.png" width="640">

#### Frissítés
Megmutatja a telepített és a legújabb GitHub kiadást a változások leírásával; az alapadmin egy kattintással telepítheti.

<img src="docs/screenshots/hu/admin-6-update.png" width="640">

## Frissítés az admin felületről

- A **Frissítés** fül ellenőrzi a beállított tároló ([GitHub kiadások](../../releases); alapból `gozie-lab/GozieDarts`) legújabb kiadását.
- Ha van újabb verzió, megjelenik a **változások leírása**. A *Telepítés* gombot **csak az alapadmin** használhatja.
- A kiadásnak tartalmaznia kell a `GozieDarts.zip` fájlt (opcionálisan `GozieDarts.zip.sha256`). Ellenőrzés: szerkezet, engedélyezett fájltípusok, útvonal-trükkök, PHP szintaxis, verzió = címke; a fájlokról **biztonsági mentés** készül az adatmappába, majd atomi csere **automatikus visszaállítással** hiba esetén.
- A `darts-config.php`, az `install.php` és az adatmappa érintetlen marad. Feltételek: PHP `zip` kiterjesztés, kimenő HTTPS, írható fájlok. Különben kézi frissítés (lásd [INSTALL.hu.md](app/INSTALL.hu.md)).
- Beállítások a `darts-config.php`-ban: `update_repo`, `update` (`false` kikapcsolja).

## Telepítés

Töltsd fel az [`app/`](app/) tartalmát (vagy a kiadás zipjét) bármely PHP 7.4+ tárhelyre (HTTPS), nyisd meg az `install.php`-t, és kövesd a varázslót (magyar / angol / német). Részletek: **[INSTALL.hu.md](app/INSTALL.hu.md)** · [English](app/INSTALL.md) · [Deutsch](app/INSTALL.de.md).

## Kiadás készítése

1. Emeld az `app/VERSION`-t, az `APP_VER`-t az `app/index.html`-ben és a `darts-vNN` gyorsítótár-nevet az `app/sw.js`-ben.
2. Írj új szakaszt a [CHANGELOG.md](CHANGELOG.md)-be.
3. `git tag vX.Y && git push --tags` – a [workflow](.github/workflows/release.yml) elkészíti a `GozieDarts.zip`-et, és közzéteszi a kiadást a changelog szövegével.

## Betűtípusok

Az *Inter* az `app/fonts/` mappában van (SIL OFL); külső betűtípus-kérés nincs.

## Felépítés

| Útvonal | Mi ez |
|---|---|
| `app/index.html` | a teljes kliens (egy fájl) |
| `app/*.php` | szerver (`api.php` + modulok), `install.php` varázsló, `updater.php` |
| `app/admin.html` | admin felület |
| `app/sw.js`, `app/manifest.webmanifest` | PWA |
| `app/fonts/` | a csomagolt betűtípusok |
| `docs/screenshots/hu/` | képernyőfotók |

## Licenc

Közkincs – lásd [LICENSE](LICENSE) ([Unlicense](https://unlicense.org)). Betűtípusok: SIL Open Font License.
