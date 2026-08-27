# Changelog

What changed in each release of ProjectSend, written for the people running it.

Versions follow [SemVer](https://semver.org/): the middle number moves when there are new features,
the last one when there are only fixes, and the first one when an upgrade needs more from you than
dropping in the new files and running the migrations.

Anything under **Upgrade notes** is something you have to do, not something we did.

## Unreleased

This section collects changes as they land; the release process turns it into a numbered entry when
a version is cut.

## 2.2.0 — 27 August 2026

A big release. Most of it closes holes in who can see what. The rest is a handful of new things.

**New**

- Google Cloud Storage can hold your files, alongside S3.
- You can set a maximum size for a zip download.
- Zip downloads no longer hold up your email.
- ProjectSend warns you if nothing is building your zip downloads.
- A deleted account's email address can be used again.

**Closed holes in who can see what**

- A staff member limited to their own clients now stays limited everywhere.
- Private notes on a publicly shared file stay private.
- Clients no longer see the names of folders they cannot open.
- The maximum file size now applies to large uploads too.
- A download limit now holds when a zip is collected.
- A large upload cannot be finished twice at once.

**Fixed**

- People whose accounts came from ProjectSend v1 can sign in again.
- Sessions no longer break behind a reverse proxy.
- Saving after your session expires takes you to the login page, not an error.
- An upload that cannot be stored now fails instead of vanishing.
- Downloads, thumbnails and share links work when files are kept in cloud storage.
- Deleting an account either finishes completely or does nothing at all.
- Creating something with a create-only role no longer ends in an error page.
- Connecting Google or Microsoft to an account you already have now works.
- Downloads work on cPanel and Plesk, where the web server is not PHP's user.
- The dashboard no longer fails on shared hosting.
- An installation built from source is no longer told to pull an image.
- One confirmation message instead of two.

**Before you upgrade, read the three notes below.** One of them needs you to do something if you
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

- **Hosting ProjectSend for other people?** Three environment variables are new, and all three are
  for you rather than for somebody running a single installation of their own.
  `PROJECTSEND_PLATFORM_MAX_STAFF_USERS` and `PROJECTSEND_PLATFORM_MAX_CLIENTS` cap how many
  accounts an installation may hold; leave them unset for no limit, which is what every ordinary
  install wants. `PROJECTSEND_TWO_FACTOR_ENFORCEMENT` seeds the two-factor policy on the very first
  boot, before the first administrator exists — it is only ever a starting value, and an
  administrator who changes it afterwards keeps their change. `php artisan projectsend:status
  --json` reports the version, the edition, the capabilities in force and the seat counts, so you
  can read all of it without opening a shell inside the application.

- **If you run behind a reverse proxy, check `TRUSTED_PROXIES`.** It is now read correctly, which it
  was not before — see the fix below. Set it in `.env`, and do not run `config:cache`, which stops
  `.env` being read at all.

### Added

- **Google Cloud Storage as a storage backend.** External storage used to mean S3 and nothing else.
  The Storage settings screen now asks which provider you are using first, and offers Google Cloud
  Storage alongside the S3-compatible option: choose it, paste a service account key with read and
  write access to your bucket, and new uploads go there. The key is stored encrypted and never shown
  again, and **Test connection** checks it can actually reach the bucket before you switch anything
  over — using a probe that works with a least-privilege key, rather than one that needs permission
  to read the bucket's own settings. Downloads and previews are handed to the visitor as a
  short-lived signed link, exactly as they already were for S3.

  Nothing changes for an existing installation. Configurations saved before this release are S3, are
  still S3, and are not asked to say so. Files already stored stay where they are — the setting
  applies to new uploads, and there is still no migration between backends.

- **A maximum size for zip downloads.** A new Settings → Downloads screen sets the largest selection
  anyone can ask for as a single zip — 2 GB out of the box, any figure you like, or 0 for no limit.
  Building an archive costs disk space and occupies the background worker for as long as it takes to
  write, so one person asking for a whole library at once used to hold up every notification email
  behind it. Ask for more than the limit and you are told how large your selection is and what the
  ceiling is, rather than simply refused; each person can have one archive being prepared at a time,
  for the same reason.

- **ProjectSend tells you if nothing is building your zip downloads.** The change below gives zip
  building its own queue, which a manual install's background worker has to be told about. Miss that
  and the failure is silent: email keeps going out, zip downloads simply never finish, and nothing
  in any log says why. Staff who can see system information now get a banner naming the problem and
  the one-line fix, so nobody has to work it out from a spinner that never stops.

- **Zip downloads no longer hold up your email.** Preparing a large archive can take a while, and it
  used to run on the same queue as everything else — so one big zip could delay every notification
  email behind it. Zip building now has a queue of its own, and the Docker images run a second
  background worker for it.

  **Manual installs:** your background worker has to be told about the new queue, or zips will never
  finish and nothing will say why. `update.sh` spots this and offers to fix the worker service for
  you, keeping a copy of the old one — so for most people there is nothing to do but say yes. If you
  update by hand, or your worker already names its own queues (the updater will say so rather than
  edit a deliberate arrangement), add `zips` to its `--queue` list and reload systemd. Docker
  installations need no change. See INSTALL.md for the two-worker setup if you would rather keep the
  two kinds of work apart.

- **A deleted account's email address can be used again.** Deleting an account keeps its record for
  a grace period before erasing it for good, and the address stays reserved until that happens — but
  only accounts that deleted *themselves* were ever scheduled for erasure. An account an
  administrator deleted sat in that state permanently, and its address could never be reused, with
  nothing on screen to explain why. Every deletion now schedules the erasure the same way, whoever
  performed it, and the staff screens explain a reserved address rather than saying only that it is
  taken: which date it frees up, or which command frees it sooner. Public registration deliberately
  keeps the plain "already taken" message, since telling a stranger the address once had an account
  here is the disclosure that message exists to avoid.

  Accounts deleted before this change keep their old state on purpose — stamping them during an
  update would quietly start a countdown to erasure that nobody chose. The console command named in
  the new message handles those.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1678](https://github.com/projectsend/projectsend/pull/1678), closing
  [#1648](https://github.com/projectsend/projectsend/issues/1648))

- **A staff role limited to its own clients now stays limited.** Several ways around that limit are
  closed together, because any one of them made the rest decorative. A role holding the "manage
  users" permission could edit its own role and simply switch the limit off; it could hand itself
  clients it was never assigned; it could promote any client on the installation to a staff account,
  which is the most far-reaching thing that can be done to a client record. Uploading into, or
  moving a file into, a folder belonging to somebody else's clients is refused too, as is browsing
  the folder pickers past your own tree. None of this was reachable with any role that ships with
  ProjectSend — each needed a custom role built on the roles screen — but the combinations are ones
  the screen offers, so anyone who built one should update.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1681](https://github.com/projectsend/projectsend/pull/1681),
  [#1694](https://github.com/projectsend/projectsend/pull/1694),
  [#1697](https://github.com/projectsend/projectsend/pull/1697),
  [#1700](https://github.com/projectsend/projectsend/pull/1700) and
  [#1702](https://github.com/projectsend/projectsend/pull/1702))

- **A public file's private notes stay private.** The comment thread on a publicly listed file is
  meant to show what any visitor sees. It was instead answering signed-in visitors as themselves, so
  simply having an account — any account — showed staff-only notes on that file, or the messages
  addressed to that file's clients. Being signed in now shows you what a visitor sees, plus your own
  comments, unless you were entitled to see the file anyway.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1695](https://github.com/projectsend/projectsend/pull/1695))

- **A client is no longer shown the names of folders they cannot open.** Browsing into a folder in
  the client portal listed every subfolder inside it, including ones shared with somebody else.
  Opening one was always refused, so what escaped was the name — which can be enough, when folders
  are named after the people they belong to.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1690](https://github.com/projectsend/projectsend/pull/1690))

- **The maximum file size now applies to large uploads.** Big files are sent in pieces, and the size
  limit was only checked against the size the sender *claimed* before sending anything. Declaring a
  tiny upload and then sending gigabytes passed every check. The assembled file is now measured
  against the limit before it is accepted.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1682](https://github.com/projectsend/projectsend/pull/1682))

- **A download limit now holds when a zip is collected.** Preparing an archive never spent anybody's
  download allowance, and only collecting one did — so an archive prepared while a file was still
  available stayed collectable after its limit was spent, and several could be held that way at
  once. The limit is now checked at the moment the archive is handed over, which is also the moment
  it is spent. Archives also record exactly which files went into them, so the download history
  counts what was actually delivered rather than re-guessing it afterwards.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1692](https://github.com/projectsend/projectsend/pull/1692))

- **Public downloads work on installations using external storage.** The public listing's download
  link always answered as though the file were on the server's own disk, so on an installation
  keeping files in object storage it pointed at a path that had never been written. Its neighbours
  on the same page — thumbnails and previews — already handled both. Now it does too.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1693](https://github.com/projectsend/projectsend/pull/1693))

- **A large upload cannot be finished twice at once.** A retry or a double submit arriving while the
  first was still assembling could interleave with it, storing bytes that no longer matched the
  file's own checksum, or recording the same upload twice. Finishing an upload now takes a lock for
  that upload, and a second attempt is turned away rather than joining in.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1686](https://github.com/projectsend/projectsend/pull/1686))

- **Deleting an account either finishes or does nothing.** Removing an account and dealing with the
  files it owns were two separate steps with nothing holding them together, so a failure in the
  second left the account gone and its files still pointing at it — most easily when the person
  chosen to inherit them was deleted in between. Both now happen together or not at all. Relatedly,
  a file's stored bytes are now removed once the deletion is committed rather than as it happens, so
  a cancelled bulk deletion no longer restores records whose files are already gone.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1688](https://github.com/projectsend/projectsend/pull/1688) and
  [#1691](https://github.com/projectsend/projectsend/pull/1691))

- **Creating something with a create-only role no longer ends in an error page.** Roles can grant
  permission to create clients, staff accounts, groups or categories without permission to edit
  them. Creating one worked, but the page it sent you to afterwards was the edit page, which such a
  role may not open — so the record was created and you were shown a permission error, with no way
  to tell whether it had worked. You now land back on the create form with the confirmation message.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1684](https://github.com/projectsend/projectsend/pull/1684))

### Fixed

- **Accounts migrated from v1 can sign in again.** On some installations brought over from
  ProjectSend Legacy, every migrated person got an error page instead of a login screen — while
  anybody whose account was created in v2 signed in perfectly. The cause was the label on the stored
  password. Older versions of PHP wrote `$2a$` or `$2b$` where newer ones write `$2y$`; all three are
  the same algorithm, but ProjectSend only recognised the last one and gave up before it had even
  looked at the password. Upgrading relabels the affected accounts in place. Nothing about anybody's
  password changes, so there is no reset mail to send and nothing for you to do — the password they
  already had simply starts working again. The migration tool no longer creates the problem in the
  first place, from version 1.0.3 onwards.
  ([#1706](https://github.com/projectsend/projectsend/issues/1706), reported by
  [@pabloalvarez44](https://github.com/pabloalvarez44))

- **Sessions no longer break behind a reverse proxy.** Signing in, or submitting the first-run setup
  form, could answer with a page-filling error instead — most visibly for anyone running behind
  Traefik, Nginx Proxy Manager or Caddy. `TRUSTED_PROXIES` was being read too early in the boot
  sequence to be seen at all, so the setting had never had any effect on a web request. Without it
  ProjectSend believed every visitor was arriving from the proxy over plain HTTP, built its links and
  cookies accordingly, and rejected the form that came back as though it had come from somewhere
  else. Docker installations that set the value as an environment variable were unaffected the whole
  time; manual installs, where the guide tells you to put it in `.env`, were not — which is why this
  looked so inconsistent. **Upgrade note:** if you run behind a proxy, set `TRUSTED_PROXIES` and do
  not run `config:cache`, which stops `.env` being read at all. Both are covered in INSTALL.md.
  ([#1672](https://github.com/projectsend/projectsend/issues/1672), reported by
  [@mstewart14](https://github.com/mstewart14); fixed by
  [@elibrachas](https://github.com/elibrachas) in
  [#1674](https://github.com/projectsend/projectsend/pull/1674))

- **Saving something after your session has expired now takes you to the login page.** Instead of
  being told to sign in again, you got an unexplained error — the dashboard's widget settings and
  several settings screens were the usual places to meet it. The cause was a detail of how browsers
  follow redirects: they repeat the original request at the new address, so "save this" became "save
  this to the login page", which the login page has no idea what to do with. It now answers in a way
  that sends the browser to read the page rather than repeat the save. The same thing could happen to
  an account that was deactivated while someone was working in it, or one being asked to set up
  two-factor authentication, and both are fixed with it.
  ([#1673](https://github.com/projectsend/projectsend/issues/1673), reported by
  [@mstewart14](https://github.com/mstewart14); found, diagnosed and fixed by
  [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1680](https://github.com/projectsend/projectsend/pull/1680))

- **An upload that cannot be stored now fails instead of disappearing.** When files are kept in
  object storage and the storage backend refuses a write — an expired key, a bucket that has been
  renamed or removed, a permission that changed underneath you — the upload used to report success
  and record the file anyway. The entry appeared in the file list, and the download it promised was
  never going to work, because the bytes had gone nowhere. The upload now stops and says so, and no
  file is recorded. Installations keeping files on local disk were never affected.

- **Downloads and thumbnails for installations using external storage.** Two places assumed every
  file sat on the server's own disk, which stopped being true the moment S3-compatible storage was
  switched on. A share link to a file held in a bucket produced a broken download, and a public
  listing could not draw a thumbnail for one at all — while the same file downloaded and previewed
  correctly everywhere else, which made it look like the share link or the listing was at fault
  rather than where the file lived. Both now read the file from wherever it actually is. Nothing
  changes for installations keeping files on local disk, which is most of them.

- **One confirmation message instead of two.** Saving a new client, system user or role showed the
  same green "Client created." twice, stacked. So did deleting one. It was only ever cosmetic —
  nothing happened twice — but it read as though something had, which is the last thing a
  confirmation should do. Saves that stay on the same screen, such as the email settings, were never
  affected.
  ([#1675](https://github.com/projectsend/projectsend/issues/1675), reported and diagnosed by
  [@denkfabrik-li](https://github.com/denkfabrik-li))

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

- **A zip download is never offered over an archive that was not written.** Archives are built in the
  background, and the writing all happens at the very end — so a source file deleted while the build
  waited its turn, or a disk that filled up, produced no archive at all while the download was still
  marked ready. Clicking it then failed with an unexplained error. The same went for a selection
  whose files had all become unavailable: an archive with nothing in it is not written to disk
  either. Both now fail the build and say why. Large archives were affected differently: a build
  taking longer than a minute was killed by the queue worker and the download simply spun forever,
  waiting for something that had already stopped. Builds now get the time they need, a build the
  queue gives up on reports itself as failed, and the partial files an interrupted build leaves
  behind are cleaned up rather than sitting on disk unnoticed.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1687](https://github.com/projectsend/projectsend/pull/1687))

- **Comment moderation now stops at the same boundary everything else does.** A staff role can be
  limited to its own assigned clients, and everything in the library respects that — listings,
  downloads, file details, and the moderation queue itself. Deleting or approving a single comment
  did not. Someone with a client-limited role who also held the comment moderation permission could
  remove any comment on the installation by its id, including conversations belonging to clients
  they were not assigned to, on files they could not open. No role that ships with ProjectSend
  combines those two things, so this needed a custom role to reach; if you have built one, it is
  worth updating for. The boundary now lives in the rule itself rather than being restated by each
  screen, which is how the gap opened in the first place.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1698](https://github.com/projectsend/projectsend/pull/1698))

- **The dashboard's recent activity now respects a limited role's boundary.** A staff role can be
  limited to its own assigned clients, and the activity page has always honoured that — showing only
  entries about files, folders and clients in that person's scope. The dashboard's Recent activity
  widget did not: it listed the eight most recent entries from the whole installation, file names
  and all, to someone who would be refused the files themselves. The Client Manager role ships with
  the permission this widget needs, so any installation using it was affected. Both screens now
  answer the same way. Nothing changes for an administrator or any unrestricted role.

- **Cached previews are no longer mistaken for stray files.** The tool that finds files sitting on
  disk with no database record knew to ignore cached thumbnails, but had never been told about the
  larger previews added alongside them. So every cached preview was listed as an unclaimed file:
  offered for import on the orphan-files screen, and deleted by the daily cleanup once past its
  grace period. Importing one also created a file entry pointing at a path the preview cache owns,
  which then vanished the next time that cache was cleared. The list of what counts as a generated
  copy is now derived from the copies themselves, so a new kind cannot be left off it again.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1683](https://github.com/projectsend/projectsend/pull/1683))

- **Group membership now respects a limited role's boundary.** A staff role can be limited to its
  own assigned clients. Adding somebody to a group, or taking them out, checked only that the person
  held the "edit groups" permission — not that the group was any of their business. Because joining a
  group hands the new member everything shared with it, someone with a limited role could put one of
  their own clients into any group on the installation and, through that client, reach files they
  had been refused a moment earlier. Approving or denying a membership request was the same write
  through a second door, and the requests screen listed every pending request by name and email,
  including clients outside the viewer's roster. All of it is now held to the same boundary the rest
  of the library uses, and the sidebar count agrees with the screen behind it. No role that ships
  with ProjectSend combines the two permissions this needed, so reaching it took a custom role.
  Nothing changes for an administrator or any unrestricted role.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1701](https://github.com/projectsend/projectsend/pull/1701))

- **Declining a group membership request now happens once.** Approving a request that had already
  been decided was refused; declining one was not, and declining is not a repeatable act. Each
  repeat re-dated the decision — which is what the client's waiting period before asking again
  counts from — so the same stale request, sent again, could keep somebody out of a group
  indefinitely without anyone deciding anything. It also wrote a second entry in the activity log
  and sent the client a second "your request was declined" email for one decision. The queue only
  ever lists requests still waiting, so nothing on screen offered this. Both actions now behave the
  same way.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1705](https://github.com/projectsend/projectsend/pull/1705))

- **The dashboard's expired-files list says whose files it is showing.** For a staff role limited to
  its own clients it lists that person's own uploads, since an expired file is already out of reach
  of the clients it was shared with. It now says so — "Your expired files", and a line explaining
  what is not in the list — rather than presenting a short list as though it were the whole picture.
  A warning about what is due to be deleted is worth nothing if it is quietly narrower than it looks.

- **A limited staff role no longer reaches every client record, or every file name on the
  dashboard.** Two more places where holding a permission was treated as holding a boundary. The
  clients screen listed every client on the installation by name and email, and a role limited to
  its own assigned clients could open, rename, or delete any of them — the same through the API.
  Separately, the dashboard's largest-files, expired-files and top-clients widgets named files and
  clients from across the whole installation, which mattered more because the Client Manager role
  that ships with ProjectSend holds the permission those widgets need. Both now use the same rule
  the rest of the library already did. Installation-wide totals stay installation-wide: a count
  carries no names. Nothing changes for an administrator or any unrestricted role.

- **Notification settings accept only the switches they offer.** Saving your notification
  preferences would store a row for any name a request happened to carry, including ones nothing in
  ProjectSend can send. Such a row was never read again and could not be seen or removed from the
  screen, so the table quietly collected entries nobody could reach. The form now checks what comes
  back against the same list it offered, so the two cannot drift apart. Nothing reachable from the
  screen changes — it only ever sends back switches it was given.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1689](https://github.com/projectsend/projectsend/pull/1689))

- **A two-factor recovery code is now spent exactly once.** Using a code removed it from your list
  by rewriting the whole list, so two sign-in attempts arriving at the same moment could each save
  their own copy and put back the code the other had just spent. Nobody could get in who was not
  already holding a valid code, but a code you had crossed off a printed sheet — or watched somebody
  type — could quietly start working again, which is the one thing recovery codes promise not to do.
  The code is now removed from the record as it stands at that moment, under a lock, so a second
  attempt cannot undo the first.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1704](https://github.com/projectsend/projectsend/pull/1704))

- **A file can no longer be filed into a folder that has been deleted.** Deleting a folder deletes
  everything inside it, so a file that lands in one afterwards sits somewhere that was already
  emptied — reachable by link and in search, but missing from the folder listing its uploader would
  look in. Uploading or moving a file into a deleted folder now says so instead, and picks up the
  case where a folder is deleted while a large upload is still transferring: the finished file lands
  at the top level rather than being thrown away, since the transfer had already happened. The
  message says the folder no longer exists rather than that the value was invalid.
  (found, diagnosed and fixed by [@denkfabrik-li](https://github.com/denkfabrik-li) in
  [#1703](https://github.com/projectsend/projectsend/pull/1703))

- **A limited staff role can no longer rename or delete a group it has no part in.** Group
  membership was already held to that boundary; the group itself was not, which was the sharper half
  — sharing a file with a group is how its members reach that file, so deleting the group takes the
  access away from every one of them, including clients outside the person's own list. A role
  limited to its own clients can still manage any group that shares nothing beyond what it can
  already see, so a group it created, or one holding its own clients, stays fully editable. Nothing
  changes for an administrator or any unrestricted role.

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

