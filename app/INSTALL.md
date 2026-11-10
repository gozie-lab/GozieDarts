# GozieDarts – installation guide

🌐 **English** · [Magyar](INSTALL.hu.md) · [Deutsch](INSTALL.de.md)

One installation = one club. You do not need to be a programmer – only web hosting that runs PHP 7.4 or newer
(almost every cheap hosting plan does).

## 1. Upload

Unpack the release zip (or copy the `app/` folder of the repository) and upload the files to your hosting
(FTP or the hosting file manager) into a folder, e.g. `darts`. The site must be reachable over **HTTPS**.

## 2. Open the installer

Open `https://your-site.example/darts/install.php` in a browser (you can switch the language at the top right:
Hungarian / English / German). Green ticks mean everything is fine. If something is marked with a red ✖,
your hosting provider's support can help (tell them which point is red).

## 3. Fill in the form

- **Club name** – shown at the top left of the start screen (optional).
- **Main colour** – colour of buttons and highlights (a darker colour is recommended, labels are white).
- **Logo** – optional (png, jpg, gif or webp); can be changed later on the admin page.
- **Registration** – open (anyone can create an account) or closed (only the admin creates accounts).
- **Data folder** – "Automatic" is fine if the installer says it is outside the web root (✔). Otherwise enter the
  full path of your own folder **outside** the web root (e.g. `/home/user/darts-data`).
- **Admin name and password** – this account manages reports and can suspend/delete players. Remember it well.

Press **Install**.

## 4. Recovery code

At the end you get a code (e.g. `ABCD-EFGH-JKMN`). Write it down – if you forget the admin password you can set a
new one with it. It is not shown again.

## 5. Done

`install.php` deletes itself. If it is still there, delete it by hand. Open `https://your-site.example/darts/` –
the club name appears at the top left. On a phone, use the browser menu's "Add to home screen" / "Install app".

## Admin page

`https://your-site.example/darts/admin.html` – sign in with the admin account. You can:

- add, delete and suspend online accounts, set new passwords (players who forgot theirs appear as a red card),
- appoint further admins (only the base admin – the one entered in the installer – can do this),
- handle reports,
- change the club name, main colour and logo.

## Local players and online accounts

- **Online account:** name + password; results are available on other devices; these players appear on the admin page.
- **Local player:** just a name saved in the browser of the device, no account. On the app's *Players* page each
  entry shows whether it is an online account (linked or not) or local only.

## Club details by hand (optional – the admin page overrides this)

Open `darts-config.php` in a text editor:

```php
'club'  => 'Club name',
'color' => '#16a34a',     // a darker colour is recommended (white labels)
'logo'  => 'logo.png',    // optional; file name of an uploaded image or an https URL
```

Changes take effect within a few seconds. See `darts-config.example.php` for all options.

## Several clubs on one server

Give every club its own folder / web address, upload the files into each and run `install.php` in each.
The data of the clubs does not mix (every installation gets its own data folder).
If you set `data_dir` by hand, use a **different** path for every club!

## Security recommendation

By default the data (accounts, matches) goes into the `darts-data…` folder, which is protected. If your hosting has a
folder outside the web root (`public_html`), it is better to put the data there:

```php
'data_dir' => '/full/path/to/darts-data',
```

in `darts-config.php` – but do this **before** the first installation, or copy the data over.

## Updating

**From the admin page (recommended):** open `admin.html` → **Update** tab. If a newer GitHub release exists, its change
notes are shown and the base admin can press *Install*. Requirements: PHP `zip` extension, outgoing HTTPS, writable
app files. A backup of the replaced files goes into `<data folder>/backup` (the last 3 are kept) and the update is
rolled back automatically on any error. `darts-config.php`, `install.php` and the data are never touched.
Options in `darts-config.php`: `'update_repo' => 'gozie-lab/GozieDarts'`, `'update' => false` (disable).

**By hand:** upload the new files over the old ones, but **do not overwrite `darts-config.php` and the `darts-data`
folder** (the release zip does not contain them). Do not upload `install.php` again.
Installed apps (PWA) notice the new version and show an "update available" banner.

## Backup

From time to time download the `darts-data` folder (FTP) – it contains all data.

## Privacy

The app has no ads and no tracking. An online account needs only a name and a password (stored hashed). The club
operator (you) is responsible for the data. All fonts are bundled – the app makes no requests to third parties
(the only outgoing request of the server is the optional update check against GitHub; disable it with `'update' => false`).
