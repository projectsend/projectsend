# Updating ProjectSend

How to move an existing installation to a newer version, for both ways of running it. If you are
installing for the first time, you want [INSTALL.md](INSTALL.md) (or [DOCKER.md](DOCKER.md))
instead.

Two rules hold everywhere in this document:

- **Back up first.** Every time, including the update you are sure about. Migrations move forwards,
  not backwards: there is no command that undoes them.
- **Read [CHANGELOG.md](CHANGELOG.md) for the version you are moving to.** Anything a release needs
  beyond the steps below is written there. If a release raises the PHP requirement, that is where it
  says so.

Which path you are on decides the rest:

| How you installed | What updating means | Manual steps |
|---|---|---|
| The official Docker image (`projectsend/projectsend`) | Pull a new image, recreate the container | None — the container migrates itself |
| Docker Compose built from source (DOCKER.md) | New code, rebuild the image | None — same entrypoint |
| A release zip on your own server (INSTALL.md) | Download the zip, run one script | `sudo ./update.sh`, and answer three questions |

ProjectSend also tells you which of these you are on: the **System** card on the dashboard prints
the update instructions for *this* server, and the notice that appears when a new version is
released links to the same thing.

---

## Before you start, whichever path you are on

1. **Back up the database.**

   ```sh
   # Docker — --single-transaction is what makes this safe on a running database
   docker compose exec -T db \
       mysqldump -u root -p"${DB_ROOT_PASSWORD:-root}" \
           --single-transaction --routines --triggers \
           projectsend > projectsend-before-update.sql

   # Your own server
   mysqldump -u projectsend -p --single-transaction --routines --triggers \
       projectsend > projectsend-before-update.sql
   ```

2. **Back up the uploads and `.env`.** On Docker these live on the `storage` volume (or the host
   directory you pointed it at — see [DOCKER.md](DOCKER.md)); on a manual install they are
   `storage/app/files/` and `.env` in the install directory.

3. **Note the version you are on**, from the dashboard's System card. If you have to go back, that
   is the image tag or zip you go back to.

A backup that has never been restored is a hope, not a backup. If you have never tried, this is a
good moment: restoring into a scratch database takes five minutes and tells you something real.

---

## Updating a Docker installation

### The official image

If you are using the published image — the `compose.example.yaml` shipped with it pins
`projectsend/projectsend:2`, which follows every 2.x release — the whole update is:

```sh
docker compose pull
docker compose up -d
```

That is the complete procedure. There is no migration step to run, no cache to clear, and nothing
to do afterwards.

If you pinned an exact version instead (`projectsend/projectsend:2.0.0`), edit the tag in your
compose file first — `pull` on a pinned tag fetches the same image you already have.

### Compose built from source

Same idea, one extra step because the image is yours to build:

```sh
docker compose down          # no -v, ever: -v deletes your data volumes
git pull                     # or unpack the new release over the directory
docker compose up -d --build
docker compose exec app composer install    # if composer.lock moved
npm ci && npm run build                     # if package-lock.json or the frontend moved
```

The last two lines are what a source checkout has that an image does not: its dependencies and its
compiled frontend live outside git, so a release that changed either leaves them stale. If a page
comes back saying ProjectSend is "not installed yet" or "not built yet", it is naming which of the
two you skipped.

### What the container does on the way up

The entrypoint runs before the web server accepts a single request, in this order:

1. Recreates any missing `storage/` directories, and generates `APP_KEY` **only if one does not
   already exist** — yours is on the storage volume and is left alone. A changed key would make
   every existing session invalid and every encrypted column unreadable.
2. Waits up to 60 seconds for the database to accept connections, so a slow-starting database is a
   pause rather than a crash loop.
3. Runs `php artisan projectsend:update` — the same command a manual install runs, and the only
   definition of what an update does. It migrates the database, restores any built-in role a new
   version added a permission to (without touching roles you customised), relinks storage, clears
   the compiled caches and records the version it applied.
4. Starts php-fpm, nginx, the queue worker and the scheduler.

The queue worker and scheduler run inside that same container, so they are replaced with it and
never keep running old code.

### Checking it worked

```sh
docker compose logs app | grep -A5 "Running migrations"
docker compose ps            # the app container should reach "healthy"
```

The first time the administrator opens ProjectSend after an update, they land on a page naming the
version now running and what the release brought, read from `CHANGELOG.md` inside the release
itself. It appears once, for the account that administers the installation; afterwards it stays
reachable from **About**, under the version line.

Then open the dashboard: the **System** card's *Version* line is the version now running, and the
"a new version is available" notice disappears on its own once the running version has caught up —
it compares against what is installed on every page load, so there is nothing to clear.

### If the container will not come up

The entrypoint stops on the first failure, so a container that restarts in a loop has told you why
in its own log:

```sh
docker compose logs app | tail -40
```

The two common ones are a database that never became reachable (`DB_HOST`, credentials, or a
database container that failed its healthcheck) and a migration that could not run. Neither leaves
a half-updated site serving traffic: nginx is not started until the entrypoint finishes.

---

## Updating a manual installation

```sh
cd /var/www/projectsend
sudo ./update.sh
```

That is the whole procedure. It asks three questions — whether to check GitHub for a newer release,
whether to download it (verifying the checksum published beside it), and whether you have a backup —
then does the rest. If you would rather fetch the zip yourself, hand it over instead:

```sh
sudo ./update.sh --zip ~/projectsend-2.1.0.zip
```

`./update.sh --check` reports what is installed and what is available, changes nothing, and does not
need root.

### What it does, in order

1. Refuses to run inside a container, where updating means pulling a new image.
2. Works out who your web server runs as, from the owner of `public/index.php`.
3. Reads the version out of the zip and refuses to go backwards — migrations only move forwards —
   unless you pass `--force`. The *same* version is accepted, and is how you restore files that were
   modified or lost.
4. Takes a database dump, if you asked for one (`--backup`).
5. Puts the site into maintenance mode, and guarantees it comes back out: if anything fails, or you
   interrupt it, the site is brought back up before the script exits.
6. Unpacks the release over your installation, leaving `.env`, `storage/` and `public/storage`
   alone, and replacing `vendor/` and `public/build/` wholesale rather than merging them.
7. Runs `php artisan projectsend:update`, which migrates the database, restores the built-in roles,
   relinks storage, clears the compiled caches, rebuilds the optional ones **if you were using
   them**, and records the version it applied.
8. Reloads PHP-FPM and restarts the queue worker.
9. Brings the site back up and tells you what is running.

### Useful options

| Option | What for |
|---|---|
| `--zip <path>` | Apply a zip you downloaded yourself. |
| `--backup` | Dump the database first, to `/var/backups/projectsend` (`--backup-dir` to change). |
| `--check` | Report versions and stop. No root needed. |
| `--user`, `--php-fpm`, `--worker` | Override what it detected. |
| `--no-restart` | Leave systemd alone. You must then reload PHP-FPM yourself. |
| `-y`, `--yes` | Unattended. Requires `--backup` or `--i-have-a-backup`. |
| `--force` | Apply an older release. Read the sentence about migrations again first. |

### Doing it by hand

The script is not magic, and there is no harm in running the steps yourself:

```sh
cd /var/www/projectsend
sudo -u www-data php artisan down
# unpack the release over this directory, keeping .env and storage/
sudo chown -R www-data:www-data .
sudo -u www-data php artisan projectsend:update
sudo systemctl reload php8.4-fpm        # not optional — see below
sudo systemctl restart projectsend-worker
sudo -u www-data php artisan up
```

**The reload is the step that matters.** With OPcache configured the way production guides recommend
(`opcache.validate_timestamps=0`, which our own Docker image uses), PHP does not re-read a file it
has already compiled. Replacing the files changes nothing for the running site: the database ends up
on the new version and the web server keeps serving the old code, while `php artisan` reports the
new version the whole time you are trying to work out why.

If it happens anyway, ProjectSend now says so: staff see a banner naming the version being served,
the version that was installed, and the command that fixes it.

---

## When it goes wrong

**The site shows the old version after updating.**
Stale OPcache — reload PHP-FPM. `php artisan` reporting the new version while the browser reads the
old one is this exact symptom, and staff now see a banner saying so, naming both versions and the
command that fixes it. `sudo ./update.sh` does the reload for you unless you passed `--no-restart`.

**500 errors on every page, right after an update.**
Usually compiled caches from the previous version. `php artisan optimize:clear`, then reload
PHP-FPM. Check `storage/logs/` for the real error before assuming.

**"Base table or view not found" or a missing-column error.**
The migrations did not run, or did not finish. Run `php artisan migrate --force` and read its output.
On Docker, `docker compose logs app`.

**A setting looks stale — an old value the settings screen does not agree with.**
`php artisan cache:clear` is safe at any time and fixes it.

**The background worker is still running old code.**
It exits on `queue:restart` only once it finishes its current job, and something has to start it
again. Check the service INSTALL.md sets up: `sudo systemctl status projectsend-worker`.

**Permission errors after unpacking.**
Step 2's `chown`. `storage/` and `bootstrap/cache/` must be writable by the web server user — the
same requirement as [INSTALL.md](INSTALL.md) step 4.

**You need to go back.**

1. Restore the code: the previous image tag (Docker), or the previous directory or zip (manual).
2. Restore the database dump you took before starting.
3. On a manual install, run `sudo -u www-data php artisan projectsend:update` afterwards, so the
   installation agrees with the code you restored. (A container does this itself on boot.) Until it
   runs, staff see the banner described above — which is correct: the database is ahead of the code.

Restore both, not one. A newer database against older code is the one combination nothing in this
application expects, because migrations only ever move forwards.

---

## Version-specific notes

Anything a particular release needs beyond this document is in [CHANGELOG.md](CHANGELOG.md), under
that version. It is worth reading before you start rather than after, particularly for a release
that changes a requirement — the PHP floor, or a new extension.
