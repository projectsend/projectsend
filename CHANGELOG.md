# Changelog

What changed in each release of ProjectSend, written for the people running it.

Versions follow [SemVer](https://semver.org/): the middle number moves when there are new features,
the last one when there are only fixes, and the first one when an upgrade needs more from you than
dropping in the new files and running the migrations.

Anything under **Upgrade notes** is something you have to do, not something we did.

## Unreleased

This section collects changes as they land; the release process turns it into a numbered entry when
a version is cut.

### Fixed

- **Connecting a provider to an account that already has one.** Signing in with Google, Microsoft or
  a custom provider worked, but attaching one to an existing account did not: the **Connect** button
  on Settings → Connected accounts appeared to do nothing at all. The button asks the server in the
  background, and the server answered by redirecting to the provider — a redirect a browser will not
  follow out of a background request to another site. The page sat there with no consent screen and
  no error to explain it, so the only reading available was that the button was dead. The server now
  tells the browser to go to the provider itself, and the flow starts as it should. Signing in from
  the login page was never affected, and neither is it now.
  ([#1676](https://github.com/projectsend/projectsend/pull/1676), found and fixed by
  [@denkfabrik-li](https://github.com/denkfabrik-li))

- **Downloads on a host where the web server is not PHP's user.** A download is not served by PHP:
  PHP checks permissions and then hands the web server the path to stream. Where the two run as
  different users — cPanel and Plesk commonly arrange it that way — the web server could not open
  the file, because uploads are written readable only by the account that wrote them. The rest of
  the site gave no sign of it: uploading worked, the library listed everything, and only downloads
  failed, in the browser as `ERR_INVALID_RESPONSE`. Setting `FILES_WEB_SERVER_READABLE=true` now
  writes uploads so the web server can read them. It is opt-in, and deliberately so — the modes it
  uses are readable by every account on the machine, which is the wrong trade on a server where the
  web server and PHP are the same user, as they are in the Docker image and on most servers people
  set up themselves. The install guide has the full procedure, including the one thing no
  application setting can fix: a PHP-FPM pool with a restrictive umask, which caps new directories
  no matter what ProjectSend asks for.
  ([#1668](https://github.com/projectsend/projectsend/issues/1668), reported by
  [@denkfabrik-li](https://github.com/denkfabrik-li))

- **An installation that builds its own containers is no longer told to pull.** ProjectSend prints
  the update instructions for the way you installed it, and it had two answers where it needed
  three: anything running in a container was handed `docker compose pull && docker compose up -d`,
  including the Compose stack that builds from a checkout of the repository. There is no image
  behind those containers to pull, so both commands ran, reported success and changed nothing — and
  the dashboard went on offering the same release. Those installations are now recognised and given
  `git pull && docker compose up -d --build` instead, with the two extra steps a checkout needs when
  a release moves its dependencies or its frontend.
  ([#1661](https://github.com/projectsend/projectsend/issues/1661), reported by
  [@mueller7382](https://github.com/mueller7382))

- **The dashboard no longer fails on shared hosting.** To decide which update instructions to print,
  ProjectSend asks whether it is running inside a container by looking for a file in the root of the
  filesystem. On shared hosting PHP is usually confined to your own directory, and looking outside it
  is treated as an error rather than as a "no" — so the one page that asks the question, the
  dashboard, returned a 500 while every other page worked. It now takes the restriction as the answer
  it always was: a server that keeps PHP inside a single directory is not our container image, and
  gets the manual update instructions, which is correct for shared hosting anyway. Nothing to change
  on your side, and no setting you would have been able to change if there were.
  ([#1663](https://github.com/projectsend/projectsend/issues/1663), reported by
  [@denkfabrik-li](https://github.com/denkfabrik-li))

- **502 Bad Gateway behind a reverse proxy.** Every page carried a `Link:` header listing its
  frontend assets, duplicating tags the page already had in its `<head>` — twenty of them on the
  login screen, more on a heavier page. nginx buffers a response's headers into a single block that
  defaults to 4 KB, so the file list, at over 6 KB of headers, was refused with `upstream sent too
  big header` and the proxy answered 502. Which pages went over depended on how many assets they
  loaded, so it looked like an intermittent fault: the login screen appeared, and then the
  application did not. The duplicate header is gone — the same pages now send under 1.3 KB — and no
  browser loses anything, because the tags it actually reads were always in the document. The
  install guide gained the proxy buffer settings for anyone on an older version or behind a proxy
  holding a tighter default.
  ([#1664](https://github.com/projectsend/projectsend/issues/1664), reported by
  [@denkfabrik-li](https://github.com/denkfabrik-li))

- **`docker logs` now shows the web server's log.** The container runs nginx, PHP-FPM, the queue
  worker and the scheduler, and all of them reported to Docker except the one you need when a
  request fails: nginx opened the log files named in its own configuration and wrote to them inside
  the container, where nothing looks. The effect was that a proxy problem produced no logs on either
  side — the reason for every 502 and every 403 existed, in a file nobody knew to open. Both its
  access and error logs now go to the container's output, and the Docker guide has a section on
  running behind a reverse proxy that says which side a given message points at.

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

