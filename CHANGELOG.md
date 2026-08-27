# Changelog

What changed in each release of ProjectSend, written for the people running it.

Versions follow [SemVer](https://semver.org/): the middle number moves when there are new features,
the last one when there are only fixes, and the first one when an upgrade needs more from you than
dropping in the new files and running the migrations.

Anything under **Upgrade notes** is something you have to do, not something we did.

## Unreleased

This section collects changes as they land; the release process turns it into a numbered entry when
a version is cut.

**Fixed**

- The plain-text version of an email no longer shows the link twice, wrapped in brackets.

## 2.2.0 — 27 August 2026

A big release. Most of it closes holes in who can see what. The rest is a handful of new things.

**New**

- Google Cloud Storage can hold your files, alongside S3.
- You can set a maximum size for a zip download.
- Zip downloads no longer hold up your email.
- ProjectSend warns you if nothing is building your zip downloads.
- A deleted account's email address can be used again.

**Closed holes in who can see what**

- A staff member limited to their own clients now stays limited everywhere: client records, file
  names, groups, comment moderation, and the dashboard's activity and expired-file lists.
- Private notes on a publicly shared file stay private.
- Clients no longer see the names of folders they cannot open.
- The maximum file size now applies to large uploads too.
- A download limit now holds when a zip is collected.
- A large upload cannot be finished twice at once.
- A two-factor recovery code can only be used once.
- Your notification settings accept only the switches the screen offers.

**Fixed**

- People whose accounts came from ProjectSend v1 can sign in again.
- Sessions no longer break behind a reverse proxy.
- No more 502 Bad Gateway behind a reverse proxy.
- Saving after your session expires takes you to the login page, not an error.
- An upload that cannot be stored now fails instead of vanishing.
- Downloads, thumbnails and share links work when files are kept in cloud storage.
- A zip download is never offered when the archive was not actually written.
- Deleting an account either finishes completely or does nothing at all.
- A file can no longer be put into a folder that has been deleted.
- Declining a group membership request now happens once, not twice.
- Creating something with a create-only role no longer ends in an error page.
- Connecting Google or Microsoft to an account you already have now works.
- Downloads work on cPanel and Plesk, where the web server is not PHP's user.
- The dashboard no longer fails on shared hosting.
- An installation built from source is no longer told to pull an image.
- `docker logs` now shows the web server's log.
- The repair tool no longer mistakes cached previews for stray files.
- One confirmation message instead of two.

**Before you upgrade, read the two notes below.** One of them needs you to do something if you
installed ProjectSend by hand.

### Upgrade notes

- **Manual installs: your background worker needs one more queue.** Building a zip download now runs
  on its own queue, so a worker started before this version watches the wrong one — it will keep
  sending email perfectly while no zip download ever finishes, and nothing in any log will say why.
  `update.sh` spots this and offers to fix the service file for you, keeping a copy of the old one,
  so for most people there is nothing to do but say yes. If you upgrade by hand, change the
  `ExecStart` line in `/etc/systemd/system/projectsend-worker.service` to read
  `queue:work --queue=default,zips …`, then `sudo systemctl daemon-reload && sudo systemctl restart
  projectsend-worker`. INSTALL.md has the full file, and the two-worker setup if you would rather
  keep the two kinds of work apart. Docker installations need no change.

  If it is ever missed, ProjectSend now says so on screen: staff who can see system information get
  a banner naming the problem and the fix.

- **If you run behind a reverse proxy, check `TRUSTED_PROXIES`.** It is now read correctly, which it
  was not before. Set it in `.env`, and do not run `config:cache`, which stops `.env` being read at
  all.

Thanks to [@denkfabrik-li](https://github.com/denkfabrik-li), who found, diagnosed and fixed most
of the boundary work above, and to [@mstewart14](https://github.com/mstewart14),
[@elibrachas](https://github.com/elibrachas), [@mueller7382](https://github.com/mueller7382) and
[@pabloalvarez44](https://github.com/pabloalvarez44) for reports and fixes.

### Issues closed since 2.1.0

The summary above is what changed. This is the paper trail, for anyone who wants to read the
original report.

- [#1627](https://github.com/projectsend/projectsend/issues/1627) — Errors while installing via Docker
- [#1648](https://github.com/projectsend/projectsend/issues/1648) — A deleted account's email address can never be used again
- [#1661](https://github.com/projectsend/projectsend/issues/1661) — Docker update instructions do not update ProjectSend when using official Compose setup
- [#1662](https://github.com/projectsend/projectsend/issues/1662) — Preview files not available on v2.1.0
- [#1663](https://github.com/projectsend/projectsend/issues/1663) — Dashboard 500s on shared hosting: container detection trips open_basedir
- [#1664](https://github.com/projectsend/projectsend/issues/1664) — INSTALL.md: the nginx-in-front-of-Apache path needs the buffer advice too
- [#1668](https://github.com/projectsend/projectsend/issues/1668) — INSTALL.md: X-Accel downloads fail when nginx and PHP-FPM run as different users
- [#1672](https://github.com/projectsend/projectsend/issues/1672) — Projectsend 2 behind Traefik issues 419 when logging in or hitting an error?
- [#1673](https://github.com/projectsend/projectsend/issues/1673) — Projectsend 2: Setting Widget Columns throws error
- [#1675](https://github.com/projectsend/projectsend/issues/1675) — Success toast shows twice after create/delete redirects
- [#1706](https://github.com/projectsend/projectsend/issues/1706) — V1 migration imports $2a$ bcrypt hashes that cause HTTP 500 on login

## 2.1.0 — 18 August 2026

Updating, mostly. ProjectSend now tells you when there is a new version, ends an update somewhere
rather than just stopping, and — on a server you run yourself — reduces the whole procedure to one
command that asks before each step. This is also the first release published as an official Docker
image.

### Added

- **Ask for an update the moment you want to know.** ProjectSend checks once a day on its own,
  which is no help to somebody who has just read that a release fixes the thing biting them. There
  is now a **Check now** button, and it says what it found rather than only that it ran.
- **Updating ends somewhere.** The first time an administrator opens ProjectSend after an update,
  they land on a page saying which version they are now on and what came with it — and pointing at
  ProjectSend's Discord, which is where release news and help actually live.
- **A first run that shows you around.** A brand-new installation greets its administrator once,
  with a short list of the things worth doing before real files go in. Steps tick themselves off as
  you do them.
- **About says when this installation was last updated**, and to which version. It is the answer to
  "when did this change?", asked after something looks different or by whoever inherited the server.
- **The database stops quietly filling up with things nobody reads.** Permanently failed emails and
  already-read notifications both grew forever. Both now have a retention window you set under
  **Housekeeping** on the Scheduler screen, with a nightly purge that honours it — thirty days and
  ninety by default, and `0` to keep everything. Unread notifications are never deleted, whatever
  their age.
- **The update is in the activity log**, filterable like everything else. Until now the biggest
  change that can happen to an installation was the one thing its history did not record.
- **One command to update, and it asks first.** *(Self-hosted)* `sudo ./update.sh` is the whole
  procedure: it offers to back up, verifies what it downloaded, and stops rather than guessing. Its
  useful options — take the backup for me, just tell me what would happen — are one click away on
  the update screen, and **[UPDATE.md](UPDATE.md)** documents the whole thing.

### Changed

- **The browser tab takes its name from your site**, not from the build, and from the moment the
  page starts drawing rather than once it has loaded.
- **Each edition points at its own home**, and hosted customers are no longer asked to donate to
  something they already pay for.
- **The Scheduler screen names its tasks in your language.** Ten of them were English on an
  otherwise translated page.

### Fixed

- **A deleted folder, file or group no longer takes its name with it.** Deleting any of the three
  left the name reserved for good: creating another with that name failed with *"The slug has already been
  taken"*, naming a conflict with a row the interface will not show you, and there was no way to
  release it. Deleting now hands the name back, and names held by things you deleted earlier are
  released when you update.
  ([#1645](https://github.com/projectsend/projectsend/issues/1645))
- **The Legacy migration tool installs with the command the guide gives you.** It is published now,
  so `composer require projectsend/v1-migration-tool` works as written on any installation, with
  nothing to add to your `composer.json`. The guide also says plainly what a zip or Docker install
  can and cannot do — see the upgrade notes.
- **A non-standard port no longer disappears from links** in email and on public pages.
- **An update no longer stops over a symlink's ownership**, and the official image runs nginx as the
  user php-fpm writes as.
- **Translations shipped by a package now reach the screen that wrote them.**

### Upgrade notes

- **Nothing is required beyond the usual update.** No new configuration, no new permissions to
  grant. The one database change runs itself.
- **There is now an official Docker image**, `projectsend/projectsend`. If you have been building
  from a clone, you can switch to it: it ships with its dependencies and frontend already compiled,
  so it needs neither Composer nor Node. `compose.example.yaml` in `docker/production/` is a
  working starting point.
- **Migrating from ProjectSend Legacy?** Read the first step of
  **[MIGRATING-FROM-V1.md](MIGRATING-FROM-V1.md)** before you start. It now differs by how you
  installed: a release zip and the official Docker image have no `/system/migrate` screen, because
  the frontend they ship was built before the tool existed, and the `projectsend:migrate:*` commands
  are the whole interface there. Nothing about the migration itself is missing.
- **Reporting a security issue** now has a front door: the **Report a vulnerability** button on the
  repository's Security tab, or `contact@projectsend.org`. See
  [SECURITY.md](SECURITY.md).

## 2.0.0 — 2026-08-14

ProjectSend, rebuilt from the ground up. This is a new application rather than an update to the one
before it: it installs fresh, and an optional tool imports your existing site into it. The previous
generation continues to be available as ProjectSend Legacy.

Everything you already rely on came across — roles and granular permissions, folders, two-factor
authentication, LDAP, single sign-on, per-file download limits, S3-compatible storage, custom
fields, per-client quotas, zip downloads, resumable uploads, thumbnails, editable email templates,
client portal themes and the activity log. What follows is what is genuinely new. **If you are
running the previous version, read the upgrade notes first.**

### Added

- **A REST API.** Files, clients, groups and comments — and, on self-hosted installations, staff
  accounts — with scoped tokens, documentation the application generates itself, and a usage
  dashboard. There was previously no programmable surface at all.
- **Comments on files.** The conversation about a file lives on the file, with a per-comment choice
  of who can see it and strict separation between clients.
- **File versions.** Mark an upload as replacing an earlier one and the two stay linked: the older
  file is marked *Outdated* everywhere, sharing is inherited, and recipients are notified.
- **A notification centre.** A bell with an unread count on every screen for staff and clients
  alike, a full history, and per-person control over what also arrives by email.
- **Folder sharing.** Share a folder with a client or group and everything inside follows, including
  files added later.
- **Per-person timezones.** Every date reads in the timezone of whoever is looking at it, detected
  once from their browser, instead of one setting for the whole installation.
- **Recoverability, and real erasure.** Deleted files and accounts can be restored. Self-service
  account deletion runs a disclosed grace period and then a genuine erasure, activity log included.
- **Scoped staff access.** "Limit to assigned clients" narrows a staff member's entire view, not
  just who they may upload to.
- **Sixteen languages, and a menu you curate.** Choose which languages your clients are offered and
  which one everybody starts in.
- **Themed email.** Four independent email themes, with a live preview of a real message.
- **Guided setup.** A two-minute first run or fully unattended provisioning, a database that
  migrates itself on boot, and upgrade instructions matching how you actually installed.

### Improved

- **Uploads survive the connection.** Pause and resume, retrying the missing pieces rather than the
  whole file — and clients and API callers get the identical pipeline staff use.
- **Zip downloads no longer tie up the server.** Built in the background, with the folder structure
  preserved inside, and still recorded against every file bundled.
- **The interface.** Dark mode, drag-and-drop moves, a details panel with sharing and history in
  tabs, and the same search-and-filter toolbar on every list — with filters in the address bar, so a
  view can be bookmarked or sent to a colleague.
- **A storage floor, not just ceilings.** A site-wide default quota that individual clients
  override, so an installation with self-registration is no longer unbounded.
- **Categories your clients can read.** Flat, cross-cutting and colour-coded, shown everywhere the
  file appears — including the client portal and public pages.

### Upgrade notes

- **This is not an in-place upgrade.** Install this release fresh, then bring your old site into it
  with the migration tool. Nothing is ever written to your old installation, it keeps running
  throughout, and any import can be undone with a single command.
- **Your clients will sign in with their email address**, not their username. Their existing
  passwords keep working, so nobody has to reset anything.
- **Three things the previous version could do are not here yet:** IP allow and deny lists, email as
  a second factor, and encryption at rest. If you depend on any of them, stay where you are for now.
- **Two accounts cannot share an email address.** The previous version allowed it, because it signed
  people in by username. The import refuses to start and names the accounts to fix, rather than
  silently merging two people into one.
- **Nested categories become flat.** Each keeps its full path as its name — "Clients / Acme /
  Invoices" — so nothing is lost and no two categories collapse into one. Rename them afterwards if
  you would rather.
- **Files encrypted at rest, or held on external cloud storage, are not imported.** They are listed
  for you individually before the run starts, and skipped rather than guessed at.

