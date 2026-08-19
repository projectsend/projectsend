# Running ProjectSend with Docker: where your data lives

Docker is the recommended way to run ProjectSend, and this page is about the one part of it that
bites people: **your files and your database do not belong to the containers, and you should be
able to prove it.** Containers are meant to be thrown away and rebuilt — that is the whole point of
them — so an upgrade, a crash, or a bad `docker compose` command must never be able to take your
data with it.

Read this before you put real files in ProjectSend, not after.

> **This page is about the official image**, `projectsend/projectsend`, started from the
> `compose.example.yaml` in [Getting started](README.md#getting-started). That is the supported way
> to run it.
>
> A **clone of this repository is a development copy, not an installation** — it builds from source,
> bind-mounts the working tree, and ships nothing pre-built. If that is what you are running, its
> setup and its data layout are [CONTRIBUTING.md](CONTRIBUTING.md), not this page.
>
> Installing without Docker, on a plain PHP server, is [INSTALL.md](INSTALL.md).

---

## The two things that matter

Everything ProjectSend cannot regenerate lives in exactly two Docker volumes:

| What | Where it is by default | Losing it means |
|---|---|---|
| **The database** | The volume mounted at `/var/lib/mysql` — `projectsend_db-data` | Everything except the files themselves: accounts, groups, permissions, share links, comments, the activity log |
| **Uploaded files, and `APP_KEY`** | The volume mounted at `/var/www/html/storage` — `projectsend_storage` | The files your clients downloaded, and the key that decrypts saved SMTP and LDAP passwords |

The second one is the one people get wrong, because it is two things in one place. The container
generates `.env` on first boot and keeps it *on the storage volume*, at `storage/.env`, symlinked
into place — precisely so `APP_KEY` survives the container being replaced. A key that changes
between restarts signs everybody out and makes every encrypted column permanently unreadable, and
nothing errors when it happens. Back up the volume and you have both halves; back up only
`storage/app/files/` and you have the files without the key.

(If you set `APP_KEY` in the environment instead, Laravel reads it from there and it wins. That is
the right move when you already manage secrets somewhere else — but then it is *that* system's
backup you are relying on.)

You do not have to work out which of these you have from memory. **The dashboard's System panel
reports where your uploaded files actually live** — a host directory, a Docker volume (named), or
the container's own filesystem — and warns you about the last two. The database is the one thing it
cannot check: it runs in its own container, and the only way for PHP to see inside that one would be
to hand it the Docker socket, which would turn any vulnerability in the application into root on
your server. That half is on you, and it is what the backup section below is for.

Two things you may be surprised to find you do **not** need to protect:

- **Redis** (`projectsend_redis-data`) holds sessions, the cache and the job queue. Losing it signs
  everyone out and drops any not-yet-sent emails or half-built zips. Annoying; not data loss.
- **Parts of `storage/app/files/`** are derived, not precious: `zips/` (built downloads, deleted
  automatically after a day), `thumbnails/` and `previews/` (rebuilt on demand the next time
  somebody looks at a file). They sit inside the volume you are backing up anyway, so the simplest
  thing is to take all of it and not think about which is which.

## The good news, and the one command to fear

Named volumes are already outside the container lifecycle. `docker compose pull`,
`docker compose down`, deleting and recreating every container — none of those touch
`projectsend_db-data` or `projectsend_storage`. Upgrading does not lose your data, and never did.

The command that *does* destroy it is:

```sh
docker compose down -v        # ← the -v deletes the named volumes
```

That flag exists to clean up a development machine. On a real installation it deletes your entire
database and every uploaded file in about a second, with no confirmation. The same goes for
`docker volume prune` and `docker system prune --volumes` when the stack happens to be down.

So the actual problem with the default setup is not fragility, it is **invisibility**: your data is
somewhere under `/var/lib/docker/volumes/`, which means most people never back it up and would not
know where to look. The rest of this page fixes that.

---

## Surviving a reboot

Every service needs a restart policy, or the Docker daemon will not start it again when the host
comes back:

```yaml
services:
  app:
    restart: unless-stopped
  db:
    restart: unless-stopped
  redis:
    restart: unless-stopped
```

`compose.example.yaml` already has this on all three. It is worth checking if you wrote your own
compose file, because the failure is silent and delayed: the stack works perfectly until the first
reboot or power cut, and then the site is simply down with no error anywhere. `depends_on` does not
cover this — it applies to `docker compose up`, not to containers the daemon brings back at boot.

```sh
docker compose ps -a          # after a reboot, everything should be Up, not Exited (0)
```

---

## Putting the data where you chose

Bind-mount both volumes to real paths on the host, so your data sits somewhere you picked, somewhere
you can see in `ls`, and somewhere your existing backup tool already knows about.

### 1. Make the directories

```sh
sudo mkdir -p /srv/projectsend/storage /srv/projectsend/mysql
```

No `chown` needed for either. The ProjectSend container recreates the directory tree it needs on
every boot and sets its own ownership (uid 1000), precisely because a bind-mounted host directory
arrives empty where a named volume arrives seeded from the image. The MySQL image does the same for
its own directory the first time it starts.

### 2. Point the compose file at them

`compose.example.yaml` is yours — you downloaded and edited it — so change the volumes in place
rather than layering an override on top:

```yaml
services:
  app:
    volumes:
      # Was: storage:/var/www/html/storage
      - /srv/projectsend/storage:/var/www/html/storage

  db:
    volumes:
      # Was: db-data:/var/lib/mysql
      - /srv/projectsend/mysql:/var/lib/mysql
```

Mount the whole `storage` directory, not `storage/app/files` inside it. Uploads are only half of
what lives there — `storage/.env` holds `APP_KEY`, and mounting one level too deep leaves the key
back inside the container where the next `docker compose down` takes it.

Then drop `storage:` and `db-data:` from the `volumes:` block at the bottom, if nothing else uses
them, and check the result before applying it — this prints the fully merged configuration:

```sh
docker compose config
```

### 3. Move the data you already have

**Skip this on a brand-new installation.** There is nothing to move; go straight to step 4.

Stop everything first. Copying a database out from under a running MySQL is how you get a backup
that restores into a corrupt table.

```sh
docker compose down          # no -v
```

A throwaway container is the tidy way to reach inside a named volume:

```sh
docker run --rm \
    -v projectsend_storage:/from \
    -v /srv/projectsend/storage:/to \
    alpine sh -c 'cd /from && cp -a . /to'

docker run --rm \
    -v projectsend_db-data:/from \
    -v /srv/projectsend/mysql:/to \
    alpine sh -c 'cd /from && cp -a . /to'
```

(Those are the volumes' real names — the `storage` and `db-data` from your compose file, prefixed
with the project name. `docker volume ls` will confirm them.)

### 4. Start, and check

```sh
docker compose up -d
```

Then prove it worked rather than assuming: log in and check the dashboard's System panel — **Files
stored on** should now read *Host directory*, and the Docker-volume warning should be gone. Then
open a file, **download it**, and upload a new one; confirm the new upload appears under
`/srv/projectsend/storage/app/files/` on the host.

Confirm the key came across too, since that is the half nothing on screen will tell you about:

```sh
grep '^APP_KEY=' /srv/projectsend/storage/.env
```

If that is empty or missing while your database has saved SMTP or LDAP credentials, stop and go
back — the container will generate a *new* key and those passwords become unreadable.

Once you are satisfied, and not before, you can reclaim the old volumes:

```sh
docker volume rm projectsend_storage projectsend_db-data
```

---

## Backing up

Bind mounts make your data visible. They do not make it backed up.

### The database

**Do not back up the MySQL directory by copying it while the database is running.** A file-level
copy of a live data directory is not a snapshot — it is a set of files captured at slightly
different moments, and it may restore into something subtly broken. Use a dump:

```sh
docker compose exec -T db sh -c \
    'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" \
        --single-transaction --routines --triggers \
        projectsend' > projectsend-$(date +%F).sql
```

`--single-transaction` is what makes this safe on a running database: the dump sees one consistent
moment in time without locking anybody out. Reading the password from the container's own
environment keeps it off your shell history and off the process list on the host.

### The files, and the key

```sh
rsync -a /srv/projectsend/storage/ /your/backup/location/storage/
```

Ordinary files, no special handling — and taking the whole directory is what picks up `.env` with
`APP_KEY` in it. That file is a few hundred bytes and it is the difference between a perfect backup
and one where the SMTP and LDAP passwords in your database are undecryptable.

If you kept the named volume instead of bind-mounting, the same content comes out through a
throwaway container:

```sh
docker run --rm -v projectsend_storage:/from -v "$PWD":/to \
    alpine tar czf /to/projectsend-storage-$(date +%F).tar.gz -C /from .
```

### Restoring

```sh
docker compose up -d db
docker compose exec -T db sh -c \
    'mysql -u root -p"$MYSQL_ROOT_PASSWORD" projectsend' < projectsend-2026-08-08.sql
sudo rsync -a /your/backup/location/storage/ /srv/projectsend/storage/
docker compose up -d
```

**Test this at least once, on a machine that is not your live one.** A backup nobody has ever
restored is a hypothesis, not a backup.

---

## Upgrading

With the data outside the containers, an upgrade touches only the containers:

```sh
docker compose pull
docker compose up -d
```

That is the whole procedure. The container runs `php artisan projectsend:update` itself on boot —
the same command a manual install runs — so it migrates the database and verifies its reference data
with no separate step. Take a database dump first anyway: migrations move forwards, not backwards,
and the one time you skip it will be the time you want it.

**[UPDATE.md](UPDATE.md)** has the rest: what the container does on its way up, how to tell it
worked, and what to do when it does not.

---

## Moving to another server

This is the payoff for everything above, and it is worth doing once deliberately so you know it
works:

1. Dump the database, and copy `/srv/projectsend/` (or the storage tarball) and the dump to the new
   machine.
2. Install Docker, put your `compose.yaml` in place, restore both as described under
   [Restoring](#restoring).
3. Point DNS at the new machine, and update `APP_URL` in your compose file if the address changed.

Bring `APP_KEY` across with the storage directory — a fresh key on the new machine leaves the site
working and the saved mail and LDAP passwords silently broken.

No export tool, no vendor involvement, nothing that only works while the old machine is alive. That
is the property worth protecting, and the reason this page exists.
