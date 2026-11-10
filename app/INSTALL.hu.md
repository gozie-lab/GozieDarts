# GozieDarts – telepítési útmutató

🌐 [English](INSTALL.md) · **Magyar** · [Deutsch](INSTALL.de.md)

Egy telepítés = egy klub. Nem kell hozzá programozni tudni, csak egy tárhely (webhosting), amin PHP fut
(7.4 vagy újabb) – szinte minden olcsó tárhely ilyen.

## 1. Feltöltés

Csomagold ki a kiadás zipjét (vagy másold ki a repó `app/` mappáját), és töltsd fel a fájlokat a tárhelyedre
(FTP-vel vagy a tárhely fájlkezelőjével) egy mappába, pl. a `darts` mappába. Fontos, hogy **HTTPS**-en
(`https://...`) legyen elérhető.

## 2. Telepítő megnyitása

Nyisd meg böngészőben: `https://sajat-oldalad.hu/darts/install.php` (jobb felül váltható a nyelv: magyar / angol /
német). Fent zöld pipákat látsz, ha minden rendben. Ha piros ✖ van, a tárhelyszolgáltatód támogatása segít
(írd meg neki, melyik pont piros).

## 3. Űrlap kitöltése

- **Klub neve** – ez jelenik meg a nyitóképen, bal felül (üresen hagyhatod).
- **Főszín** – a gombok és kiemelések színe (sötétebb ajánlott, a feliratok fehérek).
- **Logó** – nem kötelező (png, jpg, gif vagy webp), később az admin felületen is cserélhető.
- **Regisztráció** – nyitott (bárki létrehozhat fiókot) vagy zárt (csak az admin hoz létre fiókokat).
- **Adatmappa** – az „Automatikus” jó, ha a telepítő a webgyökéren kívülinek jelzi (✔). Ha nem, add meg a saját,
  webgyökéren **kívüli** mappa teljes útvonalát (pl. `/home/felhasznalo/darts-adat`).
- **Admin neve és jelszava** – ezzel a fiókkal tudod kezelni a jelentéseket, és letiltani/törölni a játékosokat.
  Jegyezd meg jól!

Nyomd meg a **Telepítés** gombot.

## 4. Helyreállító kód

A telepítés végén kapsz egy kódot (pl. `ABCD-EFGH-JKMN`). Írd fel – ha elfelejted a jelszót, ezzel tudsz újat
beállítani. Később már nem jelenik meg.

## 5. Kész

Az `install.php` magát törli. Ha mégis ott maradt, töröld kézzel! Nyisd meg a `https://sajat-oldalad.hu/darts/`
oldalt: a klub neve bal felül látszik. Telefonon a böngésző menüjében a „Hozzáadás a kezdőképernyőhöz” paranccsal
alkalmazásként is feltelepíthető.

## Admin felület

`https://sajat-oldalad.hu/darts/admin.html` – belépés az admin fiókkal. Itt tudsz:

- online fiókokat hozzáadni, törölni, letiltani, új jelszót beállítani (az elfelejtett jelszót kérőket piros kártya
  mutatja),
- további adminokat kijelölni (ezt csak az alapadmin teheti, vagyis aki az `install.php`-ban megadott admin),
- jelentéseket kezelni,
- a klub nevét, főszínét, logóját módosítani.

## Helyi játékos és online fiók

- **Online fiók:** név + jelszó, az eredmények más eszközön is elérhetők; az admin felületen ők szerepelnek.
- **Helyi játékos:** csak a készülék böngészőjében mentett név, fiók nélkül. Az app *Játékosok* oldalán mindegyik
  mellett látszik, hogy online fiók (összekötve vagy nem) vagy csak helyi.

## Klub adatai kézzel (nem kötelező, az admin felület ezt felülírja)

Nyisd meg a `darts-config.php` fájlt szövegszerkesztővel:

```php
'club'  => 'Klub neve',
'color' => '#16a34a',     // sötétebb szín ajánlott, fehér feliratok miatt
'logo'  => 'logo.png',    // nem kötelező; feltöltött képfájl neve vagy https cím
```

A változás pár másodpercen belül érvényes. Az összes beállítást lásd a `darts-config.example.php` fájlban.

## Több klub egy szerveren

Mindegyik klub kapjon külön mappát/webcímet, és mindegyikbe külön töltsd fel a fájlokat és futtasd az `install.php`-t.
Az adatok így nem keverednek (minden telepítés saját adatmappát kap). Ha a tárhelyen kézzel adsz meg `data_dir`-t,
minden klubnál **más** útvonalat írj!

## Biztonság – ajánlás

Az adatok (fiókok, meccsek) alapból a `darts-data…` mappába kerülnek, amit védelem takar. Ha a tárhelyen van a
webgyökéren (`public_html`) kívüli mappa, érdemes oda tenni:

```php
'data_dir' => '/teljes/utvonal/darts-adat',
```

a `darts-config.php`-ban – de ezt az első telepítés **előtt**, vagy az adatok átmásolásával tedd.

## Frissítés (új verzió)

**Az admin felületről (ajánlott):** `admin.html` → **Frissítés** fül. Ha van újabb GitHub kiadás, megjelenik a változások
leírása, és az alapadmin a *Telepítés* gombbal felteheti. Feltételek: PHP `zip` kiterjesztés, kimenő HTTPS, írható
alkalmazásfájlok. A lecserélt fájlokról mentés készül a `<adatmappa>/backup` mappába (az utolsó 3 marad), hiba esetén
a frissítés automatikusan visszaáll. A `darts-config.php`, az `install.php` és az adatok érintetlenek maradnak.
Beállítások a `darts-config.php`-ban: `'update_repo' => 'gozie-lab/GozieDarts'`, `'update' => false` (kikapcsolás).

**Kézzel:** töltsd fel az új fájlokat a régiek fölé, de **a `darts-config.php`-t és a `darts-data` mappát ne írd felül**
(a kiadási zip nem tartalmazza őket). Az `install.php`-t ne töltsd fel újra.
A telepített appok (PWA) észreveszik az új verziót, és „frissítés elérhető" sávot mutatnak.

## Mentés

Időnként töltsd le a `darts-data` mappát (FTP-vel) – ez tartalmaz minden adatot.

## Adatvédelem

Az app nem tartalmaz reklámot és követést. Az online fiókhoz csak név és jelszó kell (titkosítva tárolva). Az adatokért a
klub üzemeltetője (te) felel. Minden betűtípus a csomagban van – az app nem kér semmit harmadik féltől (a szerver
egyetlen kimenő kérése a nem kötelező frissítés-ellenőrzés GitHub felé; kikapcsolható: `'update' => false`).
