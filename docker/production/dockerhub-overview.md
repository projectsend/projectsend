# ProjectSend

**Share files with your clients, from your own server.**

ProjectSend is a self-hosted application for getting files to the people you work with. You upload
what you want to send, choose exactly who can see it, and each client signs in to their own private
page to download it.

No public link passed around by email, no third-party service holding your clients' documents, no
per-seat pricing. It runs on your server, and the files stay there.

This is the official image for the Community edition — free software under the GPL v2 or later.

---

## Quick start

Save this as `compose.yaml`, change the passwords and `APP_URL`, then `docker compose up -d`.

```yaml
name: projectsend

services:
  app:
    image: projectsend/projectsend:2
    restart: unless-stopped
    ports:
      # Put a TLS-terminating proxy in front of this in any real install.
      - "8080:80"
    environment:
      APP_URL: https://files.example.com
      APP_ENV: production
      APP_DEBUG: "false"

      DB_HOST: db
      DB_DATABASE: projectsend
      DB_USERNAME: projectsend
      DB_PASSWORD: change-me-database

      REDIS_HOST: redis
      CACHE_STORE: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: redis

      # Required whenever anything sits between your visitors and this
      # container — including the reverse proxy you should be running.
      TRUSTED_PROXIES: "*"

      # Optional: creates the first administrator so you skip the setup
      # screen. Ignored once any user exists.
      ADMIN_NAME: Administrator
      ADMIN_EMAIL: admin@example.com
      ADMIN_PASSWORD: change-me-admin
    volumes:
      # Every uploaded file lives here, along with the generated APP_KEY.
      - storage:/var/www/html/storage
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.4
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: projectsend
      MYSQL_USER: projectsend
      MYSQL_PASSWORD: change-me-database
      MYSQL_ROOT_PASSWORD: change-me-root
    volumes:
      - db-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "--silent"]
      interval: 5s
      timeout: 5s
      retries: 20

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - redis-data:/data

volumes:
  storage:
  db-data:
  redis-data:
```

Then open `APP_URL`. If you set `ADMIN_EMAIL` and `ADMIN_PASSWORD` the first administrator already
exists; otherwise the setup screen creates one.

The first start is slower than later ones: the container waits for MySQL to accept connections
before it migrates the database. That wait is normal, not a failure.

---

## Tags

| Tag | Moves |
| --- | --- |
| `2.1.0` | Never — an exact release |
| `2.1` | With each patch on the 2.1 line |
| `2` | With each release on the 2.x line |
| `latest` | With each release |

Pin to `2` for unattended updates that stay on a compatible line, or to an exact version if you
would rather decide when to move.

Built for **linux/amd64** and **linux/arm64**.

---

## What is in the image

One container runs the whole application under supervisord: **nginx**, **php-fpm**, the **queue
worker** and the **scheduler**. You do not need a separate worker or a cron entry.

nginx is part of the image rather than an option, because protected downloads are served with
`X-Accel-Redirect` — an fpm-only image would leave every download broken unless you reproduced the
config exactly.

**You need to bring:**

- **MySQL 8.0 or newer** (we test on 8.4 LTS). Required.
- **Redis** — recommended, but optional. Without it, set `CACHE_STORE`, `SESSION_DRIVER` and
  `QUEUE_CONNECTION` to `database` and drop the redis service; sessions, cache and jobs then live
  in MySQL.

The image packages the same release artifact as the published zip, byte for byte. It does not build
the application at image build time.

---

## Environment

Everything is read from the environment. The values below are the ones that matter; anything a
Laravel application understands works too.

| Variable | Notes |
| --- | --- |
| `APP_URL` | **The address your users reach**, not the published port. Download links and password-reset emails are built from it. |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` in production |
| `APP_KEY` | Generated on first boot and kept on the storage volume. Set it explicitly only if you manage secrets elsewhere — and never change it on a running install: it decrypts existing data. |
| `APP_TIMEZONE` | Defaults to `UTC` |
| `TRUSTED_PROXIES` | Comma-separated addresses/CIDRs, or `*`. See below. |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | MySQL connection |
| `REDIS_HOST` `REDIS_PORT` `REDIS_PASSWORD` | Redis connection |
| `CACHE_STORE` `SESSION_DRIVER` `QUEUE_CONNECTION` | `redis` or `database` |
| `MAIL_MAILER` `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` `MAIL_ENCRYPTION` `MAIL_FROM_ADDRESS` | Fallback mail settings. Easier to configure from **System → Settings → Email** once you are signed in — it has a "send test" button. |
| `FILESYSTEM_DISK` | `local` (default) or `s3` |
| `AWS_ACCESS_KEY_ID` `AWS_SECRET_ACCESS_KEY` `AWS_DEFAULT_REGION` `AWS_BUCKET` `AWS_USE_PATH_STYLE_ENDPOINT` | For S3-compatible storage |
| `ADMIN_NAME` `ADMIN_EMAIL` `ADMIN_PASSWORD` | Creates the first administrator unattended. Ignored once any user exists. |

### Behind a reverse proxy

Set `TRUSTED_PROXIES`. Without it every visitor appears to come from the proxy: the login rate
limiter treats all of your users as one attacker, and the download log records the proxy's address
instead of the real one. `*` is correct when nothing but your proxy can reach the container.

---

## Data and backups

Everything that must outlive the container is on one volume:

```
/var/www/html/storage
```

Uploaded files live there, and so does the generated `.env` holding `APP_KEY`. **Back up that
volume and your MySQL database.** Losing `APP_KEY` makes the SMTP and LDAP passwords stored in your
database undecryptable even if the rest of the backup is perfect.

To keep the data on paths you chose rather than in a named volume, bind-mount a host directory —
the container recreates the directory tree it needs at boot.

A backup nobody has ever restored is a hypothesis, not a backup. Test one, once, on a machine that
is not your live one.

---

## Updating

```sh
docker compose pull
docker compose up -d
```

The container migrates the database itself on boot — the same `php artisan projectsend:update` a
manual install runs — so there is no separate step. Take a database dump first anyway: migrations
move forwards, not backwards.

---

## What it does

**For the people you send to**

- A private area per client, showing only what has been shared with them
- Sign in with an email address, with optional two-factor authentication
- Search, filter and sort; download one file, several as a zip, or a whole folder
- Optional comments on a file, so questions live next to the thing they are about
- Email notifications when something new arrives, in their own language

**For you**

- Resumable uploads that survive a dropped connection, so large files actually arrive
- Folders, categories and client groups
- Share with one client, a whole group, or publicly — with an expiry date or a download limit
- Thumbnails and previews for images and documents
- Storage quotas per client, and custom fields for the details you keep on them
- A full activity log and download history: who got what, and when

**For the installation**

- Roles and permissions for your own team, so an uploader is not an administrator
- LDAP, social sign-in, or plain email and password
- Themes for the client-facing pages and for outgoing email
- 16 languages
- A REST API with scoped tokens and generated OpenAPI docs
- Privacy controls, including GDPR-grade account erasure with a grace period
- Local disk or S3-compatible storage

---

## Coming from ProjectSend Legacy?

The previous generation lives on at [projectsend/legacy](https://github.com/projectsend/legacy).
This is a rebuild rather than an upgrade, so moving across is an import: install fresh, then bring
your old site in with the [migration tool](https://github.com/projectsend/v1-migration-tool) —
accounts, clients, groups, categories, folders, files and history. It never writes to your old
install, and any run can be undone with a single command.

**[MIGRATING-FROM-V1.md](https://github.com/projectsend/projectsend/blob/main/MIGRATING-FROM-V1.md)
is the guide**, and it has a section for this image specifically: the migration runs from the
`projectsend:migrate:*` commands here rather than from a screen, because this image ships the
application already built and carries no Composer of its own.

---

## Documentation and support

- **Source, issues and full docs:** [github.com/projectsend/projectsend](https://github.com/projectsend/projectsend)
- **Docker guide:** [DOCKER.md](https://github.com/projectsend/projectsend/blob/main/DOCKER.md) — where your data lives, backups, moving to another server
- **Updating:** [UPDATE.md](https://github.com/projectsend/projectsend/blob/main/UPDATE.md)
- **Installing without Docker:** [INSTALL.md](https://github.com/projectsend/projectsend/blob/main/INSTALL.md)
- **Chat:** [Discord](https://discord.gg/VT9n6cyvXT)

Found a security issue? Please report it privately through GitHub's security advisories rather than
opening a public issue.

## License

GNU General Public License v2, or (at your option) any later version. Commercial licenses are
available for organizations that cannot work under copyleft terms — see
[LICENSING.md](https://github.com/projectsend/projectsend/blob/main/LICENSING.md).
