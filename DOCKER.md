# Running ProjectSend with Docker: where your data lives

Docker is the recommended way to run ProjectSend, and this page is about the one part of it that
bites people: **your files and your database do not belong to the containers, and you should be
able to prove it.** Containers are meant to be thrown away and rebuilt — that is the whole point of
them — so an upgrade, a crash, or a bad `docker compose` command must never be able to take your
data with it.

Read this before you put real files in ProjectSend, not after.

> Getting started with Docker in the first place is covered in [README](README.md#getting-started).
> Installing without Docker, on a plain PHP server, is [INSTALL.md](INSTALL.md).

---

## The three things that matter

Everything ProjectSend cannot regenerate lives in exactly three places:

| What | Where it is by default | Losing it means |
|---|---|---|
| **The database** | A Docker *named volume*, `projectsend_db-data` | Everything except the files themselves: accounts, groups, permissions, share links, comments, the activity log |
| **Uploaded files** | `storage/app/files/` in the project directory | The files your clients downloaded — gone |
| **`.env`** | The project directory | `APP_KEY`, without which saved SMTP and LDAP passwords cannot be decrypted |

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
  somebody looks at a file). They sit in the same directory as the real uploads, so the simplest
  thing is to back up all of it and not think about which is which.

## The good news, and the one command to fear

Named volumes are already outside the container lifecycle. `docker compose down`,
`docker compose up --build`, deleting and recreating every container — none of those touch
`projectsend_db-data`. Upgrading does not lose your database, and never did.

The command that *does* destroy it is:

```sh
docker compose down -v        # ← the -v deletes the named volumes
```

That flag exists to clean up a development machine. On a real installation it deletes your entire
database in about a second, with no confirmation. The same goes for `docker volume prune` and
`docker system prune --volumes` when the stack happens to be down.

So the actual problem with the default setup is not fragility, it is **invisibility**: your
database is somewhere under `/var/lib/docker/volumes/`, which means most people never back it up
and would not know where to look. The rest of this page fixes that.

---

## Putting the data where you chose

Bind-mount both to real paths on the host, so your data sits somewhere you picked, somewhere you
can see in `ls`, and somewhere your existing backup tool already knows about.

### 1. Make the directories

```sh
sudo mkdir -p /srv/projectsend/files /srv/projectsend/mysql

# The app containers run as uid 1000 by default (the WWWUSER build argument).
# If you set WWWUSER to something else in .env, use that instead.
sudo chown -R 1000:1000 /srv/projectsend/files
```

Leave `/srv/projectsend/mysql` owned by root — the MySQL image sets its own ownership the first
time it starts.

### 2. Create `compose.override.yaml`

Next to `compose.yaml`. Docker Compose reads this file automatically and merges it on top, so you
never edit the tracked `compose.yaml` and nothing you write here is lost on the next update.

```yaml
services:
  # All four app containers must see the same files directory. Missing one of
  # them is the classic mistake: uploads land in one place and downloads are
  # served from another, so every download 404s. `web` is the one people
  # forget — nginx serves the bytes itself, from
  # /var/www/html/storage/app/files/, so it needs the mount just as much as
  # the container that wrote them.
  app:
    volumes:
      - /srv/projectsend/files:/var/www/html/storage/app/files
  web:
    volumes:
      - /srv/projectsend/files:/var/www/html/storage/app/files
  worker:
    volumes:
      - /srv/projectsend/files:/var/www/html/storage/app/files
  scheduler:
    volumes:
      - /srv/projectsend/files:/var/www/html/storage/app/files

  db:
    volumes:
      - /srv/projectsend/mysql:/var/lib/mysql
```

Check the result before applying it — this prints the fully merged configuration:

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

Files, which are already on the host inside the project directory:

```sh
sudo rsync -a storage/app/files/ /srv/projectsend/files/
sudo chown -R 1000:1000 /srv/projectsend/files
```

The database, which is in the named volume. A throwaway container is the tidy way to reach inside
one:

```sh
docker run --rm \
    -v projectsend_db-data:/from \
    -v /srv/projectsend/mysql:/to \
    alpine sh -c 'cd /from && cp -a . /to'
```

(`projectsend_db-data` is the volume's real name — the `db-data` from `compose.yaml` prefixed with
the project name. `docker volume ls` will confirm it.)

### 4. Start, and check

```sh
docker compose up -d
```

Then prove it worked rather than assuming: log in and check the dashboard's System panel — **Files
stored on** should now read *Host directory*, and the Docker-volume warning should be gone. Then
open a file, **download it**, and upload a new one; confirm the new upload appears in
`/srv/projectsend/files/` on the host. A download that returns nothing means one of the four
containers is missing the mount from step 2.

Once you are satisfied, and not before, you can reclaim the old volume:

```sh
docker volume rm projectsend_db-data
```

---

## Backing up

Bind mounts make your data visible. They do not make it backed up.

### The database

**Do not back up the MySQL directory by copying it while the database is running.** A file-level
copy of a live data directory is not a snapshot — it is a set of files captured at slightly
different moments, and it may restore into something subtly broken. Use a dump:

```sh
docker compose exec -T db \
    mysqldump -u root -p"${DB_ROOT_PASSWORD:-root}" \
        --single-transaction --routines --triggers \
        projectsend > projectsend-$(date +%F).sql
```

`--single-transaction` is what makes this safe on a running database: the dump sees one consistent
moment in time without locking anybody out.

### The files

```sh
rsync -a /srv/projectsend/files/ /your/backup/location/files/
```

Ordinary files, no special handling. Restoring means copying them back and fixing ownership
(`chown -R 1000:1000`).

### `.env`

Copy it somewhere safe, once, and again whenever you change it. It is a few hundred bytes and it
holds `APP_KEY` — lose that and the SMTP and LDAP passwords stored in your database become
undecryptable, even though the rest of the backup is perfect.

### Restoring

```sh
docker compose up -d db
docker compose exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" projectsend < projectsend-2026-08-08.sql
sudo rsync -a /your/backup/location/files/ /srv/projectsend/files/
sudo chown -R 1000:1000 /srv/projectsend/files
docker compose up -d
```

**Test this at least once, on a machine that is not your live one.** A backup nobody has ever
restored is a hypothesis, not a backup.

---

## Upgrading

With the data outside the containers, an upgrade touches only the containers:

```sh
docker compose down            # again: no -v
git pull                       # or unpack the new release over the directory
docker compose up -d --build
```

The app container runs `php artisan projectsend:update` itself on boot — the same command a
manual install runs — so it migrates the database and verifies its reference data with no separate
step. Take a database dump first anyway — migrations move forwards, not
backwards, and the one time you skip it will be the time you want it.

If you run the published image rather than building your own, it is `docker compose pull` followed
by `docker compose up -d`. Either way, **[UPDATE.md](UPDATE.md)** has the whole procedure: what the
container does on its way up, how to tell it worked, and what to do when it does not.

---

## Moving to another server

This is the payoff for everything above, and it is worth doing once deliberately so you know it
works:

1. Dump the database and copy `/srv/projectsend/`, `.env` and the dump to the new machine.
2. Install Docker, put the project directory in place, restore both as described under
   [Restoring](#restoring).
3. Point DNS at the new machine, and update `APP_URL` in `.env` if the address changed.

No export tool, no vendor involvement, nothing that only works while the old machine is alive.
That is the property worth protecting, and the reason this page exists.
