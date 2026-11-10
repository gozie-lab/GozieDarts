# 🎯 GozieDarts

🌐 **English** · [Magyar](README.hu.md) · [Deutsch](README.de.md)

A darts scoring app for clubs and friends – a single-page web app / PWA with a small PHP server for online play.
No ads, no tracking, **one installation = one club**. The interface is available in Hungarian, English and German.

🤖 Built with the help of [Claude](https://claude.ai) (Anthropic).

## Features

- **X01** (301/501/701…, double-in / double-out / master, legs and sets, handicap, bots) and **Cricket**
- Fast score entry, checkout suggestions, correction of earlier turns (✏️), undo
- Statistics per match and per player: averages, 100+/140+/180, highest checkout, progress by leg
- Match history, players and profiles, **training** (Around the Clock, Bob's 27, Shanghai, checkout practice) and **tournaments**
- **Online 1-vs-1 games** with accounts, friends and challenges
- Installable as an app (PWA), local games work offline; fonts are bundled (no external requests)
- **Admin page** and **update from GitHub** (see below)

## Screenshots

<table>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/en/app-01-home.png" width="230"><br><sub>Home</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-02-new-game-players.png" width="230"><br><sub>New game – players</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-03-new-game-rules.png" width="230"><br><sub>New game – rules</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/en/app-04-game.png" width="230"><br><sub>Scoring</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-05-game-menu.png" width="230"><br><sub>Game menu</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-06-correct-turns.png" width="230"><br><sub>Correct turns</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/en/app-07-match-stats.png" width="230"><br><sub>Match statistics</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-08-cricket.png" width="230"><br><sub>Cricket</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-16-match-result.png" width="230"><br><sub>Match result</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/en/app-09-history.png" width="230"><br><sub>History</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-10-players.png" width="230"><br><sub>Players</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-10b-profile.png" width="230"><br><sub>Player profile</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/en/app-11-training.png" width="230"><br><sub>Training</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-11c-shanghai.png" width="230"><br><sub>Shanghai training</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-12-tournament.png" width="230"><br><sub>Tournament</sub></td>
</tr>
<tr>
<td align="center" width="33%"><img src="docs/screenshots/en/app-13-online.png" width="230"><br><sub>Online game</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-14-account.png" width="230"><br><sub>Account</sub></td>
<td align="center" width="33%"><img src="docs/screenshots/en/app-15-settings.png" width="230"><br><sub>Settings</sub></td>
</tr>
</table>

## Admin page

`https://your-site.example/darts/admin.html` – sign in with the admin account created in the installer. The admin page is available in Hungarian, English and German (language selector at the top).

#### Sign-in
Admin accounts only (the base admin from the installer, plus any admins they appoint).

<img src="docs/screenshots/en/admin-1-login.png" width="640">

#### Players
All online accounts with search; players who forgot their password show up as a red card so you can set a new one.

<img src="docs/screenshots/en/admin-2-players.png" width="640">

#### Player actions
Suspend, delete, set a new password, appoint or remove an admin (appointing admins is reserved for the base admin).

<img src="docs/screenshots/en/admin-3-player-actions.png" width="640">

#### Reports
Reports sent by players, with the reported content; resolve or act on them.

<img src="docs/screenshots/en/admin-4-reports.png" width="640">

#### Club
Club name, main colour and logo – changes apply immediately, no file editing needed.

<img src="docs/screenshots/en/admin-5-club.png" width="640">

#### Update
Shows the installed and the latest GitHub release with its change notes; the base admin can install it with one click.

<img src="docs/screenshots/en/admin-6-update.png" width="640">

## Updating from the admin page

- The **Update** tab checks the latest [GitHub release](../../releases) of the configured repository (`gozie-lab/GozieDarts` by default).
- If a newer version exists, its **change notes** are shown. **Only the base admin** can press *Install*.
- The release must contain the asset `GozieDarts.zip` (optionally `GozieDarts.zip.sha256`). It is validated (layout, allowed file types, no path tricks, PHP syntax, version = tag), your files are **backed up** into the data folder, then replaced atomically with **automatic rollback** on any error.
- `darts-config.php`, `install.php` and the data folder are never touched. Requirements: PHP `zip` extension, outgoing HTTPS, writable app files. Otherwise update by hand (see [INSTALL.md](app/INSTALL.md)).
- Options in `darts-config.php`: `update_repo`, `update` (`false` disables the feature).

## Install

Upload the contents of [`app/`](app/) (or the release zip) to any PHP 7.4+ hosting (HTTPS), open `install.php` and follow the wizard (Hungarian / English / German). Details: **[INSTALL.md](app/INSTALL.md)** · [magyar](app/INSTALL.hu.md) · [Deutsch](app/INSTALL.de.md).

## Making a release

1. Bump `app/VERSION`, `APP_VER` in `app/index.html` and the `darts-vNN` cache name in `app/sw.js`.
2. Add a section to [CHANGELOG.md](CHANGELOG.md).
3. `git tag vX.Y && git push --tags` – the [workflow](.github/workflows/release.yml) builds `GozieDarts.zip` and publishes the release with the changelog as notes.

## Fonts

*Inter* is bundled in `app/fonts/` (SIL OFL); no external font requests are made.

## Layout

| Path | What |
|---|---|
| `app/index.html` | the whole client app (single file) |
| `app/*.php` | server (`api.php` + modules), `install.php` wizard, `updater.php` |
| `app/admin.html` | admin page |
| `app/sw.js`, `app/manifest.webmanifest` | PWA |
| `app/fonts/` | bundled fonts |
| `docs/screenshots/en/` | screenshots |

## License

Public domain – see [LICENSE](LICENSE) ([Unlicense](https://unlicense.org)). Fonts: SIL Open Font License.
