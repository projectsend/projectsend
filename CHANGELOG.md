# Changelog

What changed in each release of ProjectSend, written for the people running it.

Versions follow [SemVer](https://semver.org/): the middle number moves when there are new features,
the last one when there are only fixes, and the first one when an upgrade needs more from you than
dropping in the new files and running the migrations.

Anything under **Upgrade notes** is something you have to do, not something we did.

## Unreleased

This section collects changes as they land; the release process turns it into a numbered entry
when a version is cut.

**Closed holes in who can see what**

- A staff member limited to their own assigned clients could read the names of other clients out of
  file details. Sharing means a file can reach somebody through one client while it was uploaded by
  another, or while it is also shared with another. That is normal and the file is theirs to open —
  but the uploader's name, the other recipient's name, and both their ID numbers were being sent
  along with it, on the library list, the file's edit page, the details panel, the per-client file
  list, and the matching API responses. A group holding none of their clients was named the same
  way. The file list could also be filtered by uploader, which answered "does this client of yours
  share files with that client of mine" without naming anybody.

  Those names are now left out for a limited staff member, and the uploader filter no longer answers
  for a client they are not assigned to. Administrators and any unrestricted role see exactly what
  they saw before.

  **Who this affected.** Only installations using the Client Manager role, or a custom role with
  "Limit to assigned clients" switched on, and only where files are shared with more than one client
  or through groups. No files, downloads or credentials were reachable this way — a file belonging
  to a client outside the roster was refused before, and still is.

  Reported by [@Noorkhalel](https://github.com/Noorkhalel) (GHSA-whmp-p9hv-r7j7).

## 2.3.0 — 1 September 2026

If you run ProjectSend on Apache or LiteSpeed, this is the release to take. It installed fine on
both before. Then every download arrived empty and every thumbnail was broken. That is fixed, and
you do not have to configure anything. Installations on nginx were never affected and nothing
changes for them.

The rest is mostly security work. Most of it is the same kind of thing: a screen or an API endpoint
that showed a little more than the person asking was allowed to see.

**New**

- **Downloads work on any web server.** Your files sit outside the web root, so ProjectSend checks
  permission on every download before anything is sent. The fast way to finish is to hand the file
  to the web server. Each web server wants that asked for differently, and until now ProjectSend
  only knew how to ask nginx. On Apache and LiteSpeed it asked anyway, nothing answered, and the
  visitor got an empty file. Now it works out what it is talking to. If it cannot hand the file
  over, it sends the file itself, which is slower under load but works everywhere.
- **Apache and LiteSpeed can still have the fast version.** Install `mod_xsendfile` (LiteSpeed
  needs no module), point `XSendFilePath` at your storage directory, and set
  `PROJECTSEND_FILE_DELIVERY=xsendfile`. See the upgrade notes.
- **The dashboard tells you which way downloads are going out.** If PHP is sending them, there is a
  warning next to it and a short explanation of what that costs you and how to change it. This is
  the kind of thing that is invisible until the day the site falls over, so it says so up front.
- **Your logo and your watermark, on every installation.** Upload a logo and it replaces ours in
  the sidebar and on your public pages. Add a watermark and it goes on the thumbnails and previews
  your clients and visitors see. Staff still see the originals, and the watermark is never written
  into the stored file, so you can turn it off again.
- **You can find out which build you are running.** Two images can say "2.2.1" and contain
  different code. `projectsend:status` now reports the commit it was built from.
- **You will know if the nightly jobs stop running.** When the scheduler dies, nothing looks wrong.
  You find out weeks later, when a file you expired is still downloadable. ProjectSend now reports
  when its scheduled work last ran and whether any of it failed.
- **You get told when the mailbox stops working**, even when a send noticed the problem before the
  scheduled check did.

**Closed holes in who can see what**

- [#1745](https://github.com/projectsend/projectsend/pull/1745) — Gate the comment moderation
  surfaces on reading, not just on the library. Permission to moderate comments was letting somebody
  read them, which is not the same thing: on the moderation screen and through the API, a role that
  could moderate comments but could not open any file was shown every comment in the installation —
  the text, staff-only notes, the client each conversation belongs to, and a visitor's IP address —
  about files it would be refused on. Approving a comment over the API handed back its body the same
  way.

  **Who this affected.** Only installations with a custom role built that way. None of the roles
  ProjectSend ships is affected: Account Manager, the only one that moderates comments, can read
  files as well, and so can a System Administrator. If you did build such a role, it can no longer
  moderate — give it one of the file permissions (upload, edit files, or edit other people's files)
  and it works again, now seeing only the comments on files it can actually open.

- [#1759](https://github.com/projectsend/projectsend/pull/1759) — Publish the example Docker
  quickstart on the loopback address instead of every network interface. The example set
  `TRUSTED_PROXIES: "*"`, which tells ProjectSend to believe the client address forwarded by
  whoever connects to it. That is right behind a reverse proxy and wrong when anyone can reach the
  container directly, because then anyone can claim any address: enough to walk past the login
  lockout, every rate limit, and the address written to the download log and to guest comments.

  **Who this affected.** Installations started from `compose.example.yaml` or from the Docker Hub
  page, where port 8080 was reachable from outside the machine. A published Docker port is not
  covered by a host firewall such as `ufw`, so this was often open without anyone intending it.

- [#1760](https://github.com/projectsend/projectsend/pull/1760) — Have the Docker image default to
  production. On first boot the image copied its settings from the development template, which sets
  `APP_ENV=local` and `APP_DEBUG=true`. Two things followed that you could not see from inside the
  application: every server error showed its stack trace — file, line and surrounding source — to
  whoever triggered it, signed in or not; and **"reject known-breached passwords" never actually
  ran**, while the security settings screen went on reporting it as switched on.

  **Who this affected.** Anyone who started the container without setting those two values: a plain
  `docker run` with a database address, the Portainer, unRAID and TrueNAS templates, or a Kubernetes
  manifest naming only the database and `APP_URL`. Installations using `compose.example.yaml`, which
  sets both correctly, were never affected.

- The client portal dashboard lists only files that client can open. The API dashboard's recent
  activity is cut the same way.
- Three lists were showing more than the viewer was allowed to see: the reassignment picker, the
  account conversion list, and the membership an API member write handed back.
- Mail and storage credentials no longer end up in the boot configuration cache. A settings form
  that gets rejected no longer sends the credential back to the browser.
- Connecting a sign-in provider asks for your password again. Every password prompt in front of an
  account now has its own rate limit instead of sharing one. A two-factor code is claimed in a
  single step, so the same code cannot be used twice.
- An expired file no longer locks a whole group shut for staff assigned to particular clients. A
  shared folder's contents count towards what a client can reach. A client is added to the roster
  of the staff member who created them.
- Whether something is an API request is decided by the route, not by a header the caller sets.
- The interface font is served from your own installation. Loading a page no longer tells a font
  CDN who is reading it.
- A stored filename can no longer push a control character into a response header.

**Fixed**

- The zip progress bar stops polling when you leave the page.
- A zip that fails to build no longer tells the person who asked for it why, in the server's words.
- Previews are written to a temporary file first, so a half-written one is never served. A file's
  previews are deleted even when its storage cannot be reached.
- An expiry date no longer moves because somebody else saved the file at the same time. Setting one
  through the API means what it means on the web form.
- Updating a client through the API no longer wipes custom fields the request never mentioned.
- The transfers chart lines up with the timezone its data is stored in.
- Creating an account over a deleted one's email address is refused instead of crashing.
- A comment still shows who wrote it after that account is deleted.
- Marking a file as a new version no longer emails people about a file they already had.
- The password reset and confirm-password screens say where the account's password actually lives,
  which matters if you use LDAP or a sign-in provider.
- A refused upload names the quota you are actually up against. A bulk edit that is refused says
  which permission was missing.
- Uploaded folders get the permissions the storage library actually asks for.
- The public preview log no longer records the same view repeatedly.
- Updating with `update.sh` no longer silently switches off route, event and view caching. The
  script wiped the compiled caches while replacing the files, which is also how ProjectSend
  recognised that you had cached them in the first place — so it rebuilt nothing, and every update
  quietly left the site slower than the install instructions promised.
- Every new screen in this release is translated into all sixteen languages.

**Before you upgrade, read the notes below.**

### Upgrade notes

- **This upgrade adds two indexes to the activity log, and on a big installation that takes
  minutes.** It is the slowest part. Nothing goes offline while it runs — the application keeps
  answering — but do not expect the migration to finish in seconds.
- **On Apache or LiteSpeed you need to do nothing, but there is something worth doing.** Downloads
  will start working on their own. PHP will be sending them, which ties up a worker process for the
  whole of each download. That is fine on a quiet site and not fine on a busy one. To move to the
  fast path: install `mod_xsendfile` (LiteSpeed needs no module), allow your storage directory with
  `XSendFilePath`, then set `PROJECTSEND_FILE_DELIVERY=xsendfile` in `.env`. The dashboard will
  confirm the change.

- **If you copied the example Docker file, `http://<your-server-ip>:8080` will stop answering.**
  That is the change. Reach the application through your reverse proxy, as `APP_URL` describes. If
  your proxy runs on a different machine, publish the port on the interface it arrives from and
  replace `TRUSTED_PROXIES: "*"` with that address or subnet — the two settings only make sense
  together.

- **Docker: `APP_ENV` and `APP_DEBUG` set inside `storage/.env` no longer take effect.** The image
  now sets them itself, and a real environment variable always beats that file. If you had turned
  debug on by editing `storage/.env`, pass `-e APP_DEBUG=true` (or `environment:` in compose)
  instead. Anything you already set that way keeps working unchanged.

Thanks to [@denkfabrik-li](https://github.com/denkfabrik-li), who wrote all forty-four pull
requests in this release, and to [@prbt2016](https://github.com/prbt2016), who reported the Apache
download failure that started the delivery work.

### Pull requests merged since 2.2.1

The summary above is what changed. This is the paper trail, for anyone who wants to read the
original change. No issues were closed in this cycle — the work arrived as pull requests.

- [#1718](https://github.com/projectsend/projectsend/pull/1718) — Narrow the reassignment picker to what a viewer may see
- [#1719](https://github.com/projectsend/projectsend/pull/1719) — Count a shared folder's contents as reach, not just the folder
- [#1720](https://github.com/projectsend/projectsend/pull/1720) — Stop an expired file locking a group shut for a scoped staff member
- [#1721](https://github.com/projectsend/projectsend/pull/1721) — Scope the API dashboard's recent actions to what the viewer may read
- [#1722](https://github.com/projectsend/projectsend/pull/1722) — Show the portal dashboard the files a client can actually open
- [#1723](https://github.com/projectsend/projectsend/pull/1723) — Stop a client PATCH clearing custom fields it never mentioned
- [#1725](https://github.com/projectsend/projectsend/pull/1725) — Write a rendition through a temporary file, and never serve an empty one
- [#1726](https://github.com/projectsend/projectsend/pull/1726) — Delete a file's renditions even when its own disk cannot be resolved
- [#1727](https://github.com/projectsend/projectsend/pull/1727) — Give an API expiry date the same meaning the web gives it
- [#1728](https://github.com/projectsend/projectsend/pull/1728) — Stop an expiry moving because somebody else saved the file
- [#1729](https://github.com/projectsend/projectsend/pull/1729) — Decide what is an API request from the route, not from the caller's headers
- [#1730](https://github.com/projectsend/projectsend/pull/1730) — Refuse to provision over a deleted account's address instead of crashing
- [#1731](https://github.com/projectsend/projectsend/pull/1731) — Fail a zip build without handing the requester the server's reason
- [#1732](https://github.com/projectsend/projectsend/pull/1732) — Debounce the public preview log the way the signed-in one already is
- [#1734](https://github.com/projectsend/projectsend/pull/1734) — Name the quota a client is actually held to when an upload is refused
- [#1735](https://github.com/projectsend/projectsend/pull/1735) — Stop an editable-once checkbox locking before anybody ticks it
- [#1736](https://github.com/projectsend/projectsend/pull/1736) — Put a client on the roster of the scoped staff member who created them
- [#1737](https://github.com/projectsend/projectsend/pull/1737) — Compare the transfers window against the column's own timezone
- [#1738](https://github.com/projectsend/projectsend/pull/1738) — Claim a TOTP code atomically instead of checking then writing
- [#1739](https://github.com/projectsend/projectsend/pull/1739) — Refresh a mailbox on the schedule under the lock a send would hold
- [#1740](https://github.com/projectsend/projectsend/pull/1740) — Leave the caches update.sh's own update command needs to see
- [#1741](https://github.com/projectsend/projectsend/pull/1741) — Ask about the zips queue on every path that could answer it
- [#1742](https://github.com/projectsend/projectsend/pull/1742) — Set the directory permission Flysystem actually reads
- [#1743](https://github.com/projectsend/projectsend/pull/1743) — Check the read half of the redirect rule at every door, not one
- [#1744](https://github.com/projectsend/projectsend/pull/1744) — Stop a version link telling people about a file they already had
- [#1745](https://github.com/projectsend/projectsend/pull/1745) — Gate the comment moderation surfaces on reading, not just on the library
- [#1746](https://github.com/projectsend/projectsend/pull/1746) — Say what expiry does to a client-scoped staff member's library
- [#1747](https://github.com/projectsend/projectsend/pull/1747) — Say which permission a bulk edit was actually missing
- [#1748](https://github.com/projectsend/projectsend/pull/1748) — Let a password reset know where the account's credentials live
- [#1749](https://github.com/projectsend/projectsend/pull/1749) — A deleted account is still the person who wrote the comment
- [#1750](https://github.com/projectsend/projectsend/pull/1750) — Tell the admins the mailbox is dead, even when a send noticed first
- [#1751](https://github.com/projectsend/projectsend/pull/1751) — Keep the mail and storage credentials out of the boot-config cache
- [#1752](https://github.com/projectsend/projectsend/pull/1752) — Bound the two preference endpoints by their own registries
- [#1753](https://github.com/projectsend/projectsend/pull/1753) — Narrow the conversion list to the clients its own refusal allows
- [#1754](https://github.com/projectsend/projectsend/pull/1754) — Narrow the membership an API member write hands back
- [#1755](https://github.com/projectsend/projectsend/pull/1755) — Give every password check in front of an account its own bucket
- [#1756](https://github.com/projectsend/projectsend/pull/1756) — Make linking a provider re-prove the password
- [#1757](https://github.com/projectsend/projectsend/pull/1757) — Stop a rejected settings form flashing the credential it carried
- [#1758](https://github.com/projectsend/projectsend/pull/1758) — Let the confirm-password screen ask where the password lives
- [#1759](https://github.com/projectsend/projectsend/pull/1759) — Publish the quickstart on loopback, since it trusts any proxy
- [#1760](https://github.com/projectsend/projectsend/pull/1760) — Have the production image default to production
- [#1761](https://github.com/projectsend/projectsend/pull/1761) — Serve the interface font from the installation, not from a font CDN
- [#1762](https://github.com/projectsend/projectsend/pull/1762) — Run the auth and settings screens through the translator
- [#1763](https://github.com/projectsend/projectsend/pull/1763) — Stop the zip poll when its page goes away
- [#1764](https://github.com/projectsend/projectsend/pull/1764) — Honour Laravel's placeholder case convention in t()

## 2.2.1 — 28 August 2026

A security release. Most of it closes ways somebody could reach past a boundary the rest of the
application already enforced — including two that could lock you out of your own installation.

**Merged**

- [#1708](https://github.com/projectsend/projectsend/pull/1708) — Let an enforced user reach the far side of the confirm-password screen
- [#1716](https://github.com/projectsend/projectsend/pull/1716) — Refuse the last administrator deleting themselves, and keep setup shut
- [#1710](https://github.com/projectsend/projectsend/pull/1710) — Stop a folder deleting the files inside it that its owner may not delete
- [#1714](https://github.com/projectsend/projectsend/pull/1714) — Hold the group edit screen to the same library boundary as the rest
- [#1717](https://github.com/projectsend/projectsend/pull/1717) — Keep a private reply private after the client is deleted
- [#1713](https://github.com/projectsend/projectsend/pull/1713) — Refuse self-deactivation over the API however the boolean is written
- [#1709](https://github.com/projectsend/projectsend/pull/1709) — Ask the seat cap where a pending client is approved through edit()
- [#1715](https://github.com/projectsend/projectsend/pull/1715) — Add a file to a zip once, however many ways the selection reaches it
- [#1707](https://github.com/projectsend/projectsend/pull/1707) — Leave the test workflow one concurrency block, so it parses again
- [#1711](https://github.com/projectsend/projectsend/pull/1711) — Stop the update tests emptying bootstrap/cache for every other worker
- [#1712](https://github.com/projectsend/projectsend/pull/1712) — Make the storage durability dashboard test assert the verdict

**Also fixed**

- The plain-text version of an email no longer shows the link twice, wrapped in brackets.
- The message you get when an account would exceed a limit no longer reads "limited to 1 staff
  accounts".

### Upgrade notes

- **Nothing to do.** Drop in the new files and run `php artisan migrate` as usual; this release adds
  no migrations, no settings and no new environment values.

- **One thing changes behaviour.** If somebody on your team has been deleting a folder as a way of
  clearing out files other people uploaded, that now refuses and says how many files are in the way.
  It is the same rule the file list has always applied one screen over — the folder was the way
  around it, and what it removed was not recoverable.

Thanks to [@denkfabrik-li](https://github.com/denkfabrik-li), who reported, diagnosed and fixed
every one of the above.

### Issues closed since 2.2.0

The summary above is what changed. This is the paper trail, for anyone who wants to read the
original report.

- [#1706](https://github.com/projectsend/projectsend/issues/1706) — V1 migration imports $2a$ bcrypt hashes that cause HTTP 500 on login

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

