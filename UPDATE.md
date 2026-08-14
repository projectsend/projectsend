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
| A release zip on your own server (INSTALL.md) | Replace the files, run five commands | All of them, including a PHP-FPM reload |

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
```

### What the container does on the way up

The entrypoint runs before the web server accepts a single request, in this order:

1. Recreates any missing `storage/` directories, and generates `APP_KEY` **only if one does not
   already exist** — yours is on the storage volume and is left alone. A changed key would make
   every existing session invalid and every encrypted column unreadable.
2. Waits up to 60 seconds for the database to accept connections, so a slow-starting database is a
   pause rather than a crash loop.
3. Runs `php artisan migrate --force`.
4. Runs `php artisan storage:link` and `php artisan projectsend:ensure-roles` — the latter teaches
   the built-in roles about any permission the new version added, without touching roles you have
   customised.
5. Clears compiled views, then starts php-fpm, nginx, the queue worker and the scheduler.

The queue worker and scheduler run inside that same container, so they are replaced with it and
never keep running old code.

### Checking it worked

```sh
docker compose logs app | grep -A5 "Running migrations"
docker compose ps            # the app container should reach "healthy"
```

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

This is the path with steps you have to perform, and one of them — the PHP-FPM reload — is easy to
miss and hard to diagnose. The order below matters.

Run everything as the user your web server runs as (`www-data` in INSTALL.md's examples).

### 1. Take the site down

```sh
cd /var/www/projectsend
sudo -u www-data php artisan down
```

Visitors get a maintenance page instead of a half-updated application.

### 2. Replace the files

The release zip is flat — its contents unpack straight into the install directory, with no wrapper
folder. It contains no `.env`, no uploads, and no `public/storage` symlink, so unpacking it cannot
take yours with it.

```sh
cd /tmp
unzip projectsend-2.0.1.zip -d projectsend-new
sudo cp -a projectsend-new/. /var/www/projectsend/
sudo chown -R www-data:www-data /var/www/projectsend
```

**A cleaner alternative, if you can:** unpack the new release into a directory of its own, copy your
`.env` and `storage/` across, and swap the two directories. Copying over an existing install leaves
behind any file the new version deleted; swapping directories does not, and rolling back is renaming
one directory instead of restoring an archive.

Either way, `storage/` and `.env` are yours and must survive. Everything else in the tree comes from
the zip.

### 3. Run the updates

```sh
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan projectsend:ensure-roles
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan queue:restart
```

`projectsend:ensure-roles` gives the built-in roles any permission the new version added. It never
touches a permission you changed yourself.

`queue:restart` asks the background worker to finish its current job and exit; whatever supervises
it (systemd, supervisor) starts a fresh one on the new code. The cron entry that drives the
scheduler needs nothing — it starts a new process every minute anyway.

### 4. Reload PHP-FPM

```sh
sudo systemctl reload php8.4-fpm
```

**Do not skip this.** If your server has OPcache configured the way production guides recommend
(`opcache.validate_timestamps=0`, which is also what our own Docker image uses), PHP does not
re-read a file it has already compiled. Replacing the files changes nothing for the running site: the
database is on the new version and the web server is still executing the old code. `php artisan`
shows you the new version from the command line while every visitor gets the old one, which is a
miserable thing to debug.

If you have never touched your OPcache settings, the reload is harmless — do it anyway rather than
finding out which kind of server you have during an update.

### 5. Put the caches back, if you use them

`optimize:clear` in step 3 cleared the optional caches from INSTALL.md's *Making it faster*
section. If you had run them, run them again now:

```sh
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

Still **not** `config:cache` — see [INSTALL.md](INSTALL.md#one-command-to-skip-configcache) for why
that one stays off on this application.

### 6. Bring it back

```sh
sudo -u www-data php artisan up
```

### 7. Check it worked

- The dashboard's **System** card shows the new version. If it still shows the old one, step 4 did
  not happen — reload PHP-FPM and reload the page.
- **System → Settings → Scheduler** shows tasks running and no new failures.
- Download a file. It is the one path that goes through nginx, PHP and your storage at once.

---

## When it goes wrong

**The site shows the old version after updating.**
Stale OPcache — step 4. `php artisan` reading the new version while the browser reads the old one is
this exact symptom.

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

Restore both, not one. A newer database against older code is the one combination nothing in this
application expects, because migrations only ever move forwards.

---

## Version-specific notes

Anything a particular release needs beyond this document is in [CHANGELOG.md](CHANGELOG.md), under
that version. It is worth reading before you start rather than after, particularly for a release
that changes a requirement — the PHP floor, or a new extension.
