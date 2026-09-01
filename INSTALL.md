# Installing ProjectSend

This guide is for installing ProjectSend **manually on your own server**, from the `.zip` file
published with each release.

If you can run Docker, use Docker instead — it is one command, and everything on this page
(PHP extensions, the web server, the background worker, the scheduled tasks) is already wired up
for you. See [the Docker instructions](README.md#getting-started), and
[DOCKER.md](DOCKER.md) for keeping your database and uploads outside the containers. Come back here
if Docker is not an option on your hosting.

The whole thing takes about ten minutes. You will need shell access to the server and the ability
to create a database — this is not an install you can do over FTP alone.

---

## What you need

| | |
|---|---|
| **PHP** | 8.4 or newer, both the command-line PHP and PHP-FPM |
| **PHP extensions** | `bcmath` `ctype` `curl` `dom` `fileinfo` `filter` `gd` `iconv` `intl` `json` `ldap` `mbstring` `openssl` `pcntl` `pdo_mysql` `session` `simplexml` `tokenizer` `zip` |
| **Database** | MySQL 8.0 or newer (we test on 8.4 LTS) |
| **Web server** | Any, with PHP-FPM. **nginx is strongly recommended** — see the note below |
| **Disk space** | The app itself is small; plan for whatever your users will upload |

A few notes on that list:

- **`ldap` is required even if you never use LDAP.** One of the libraries ProjectSend depends on
  declares it, so PHP will refuse to start the app without it. On Debian/Ubuntu it is
  `php8.4-ldap`; on RHEL-family systems, `php-ldap`.
- **nginx is recommended, not required.** ProjectSend runs on Apache and LiteSpeed too, and
  downloads work on them out of the box. What differs is *how* the bytes are sent: on nginx the
  web server sends them, and everywhere else PHP does, which costs a worker process for the
  duration of every download. See [How downloads are sent](#how-downloads-are-sent) before you
  commit to a server — it is a capacity decision, not a compatibility one.
- **Redis is optional.** The Docker setup uses it, but a manual install works fine with the
  database for sessions, cache and queues. If you already have Redis, see
  [Optional extras](#optional-extras) below.

### How downloads are sent

Your uploaded files do not live under `public/`. They sit in `storage/app/files/`, outside the web
root, where no URL can reach them — which is the whole point: a file is only yours to download if
ProjectSend says so, and a file sitting in a guessable public folder has already lost that
argument.

So every download has to pass through a permission check in PHP first. What happens *after* that
check passes is the thing this section is about, and ProjectSend can do it two ways.

**PHP sends the file.** It opens the file and writes it out to the visitor. This works on every
web server and needs no configuration, which is why it is what ProjectSend falls back to. The cost
is that one PHP worker process is occupied for the whole of each download — three minutes for a
large file on a slow connection is three minutes that worker cannot answer anything else. A
handful of concurrent large downloads can therefore occupy every worker you have and the site
stops responding, with the processor idle and the workers all waiting on network transfers.

**The web server sends the file.** PHP answers with an empty response and a header naming the
file, and finishes immediately; the web server streams the bytes with the same code it uses for
any static file — `sendfile`, byte ranges, resume support, no PHP process held open — and the
visitor never sees the real path. This is what you want on anything busy.

The second option needs a header, and **each web server reads a different one**, which is why
ProjectSend has to know which one it is talking to. It works this out from the server itself and
you can override it.

| Your server | What ProjectSend does | What you need to configure |
|---|---|---|
| nginx | `X-Accel-Redirect` | The `location /protected-files/` block in [step 6](#step-6--point-your-web-server-at-it). Detected automatically |
| Apache | PHP sends the file, unless you enable `mod_xsendfile` | See below |
| LiteSpeed / OpenLiteSpeed | PHP sends the file, unless you turn on X-Sendfile | See below |
| Anything else | PHP sends the file | Nothing |

**The dashboard tells you which one is in use.** The System panel has a "Downloads sent by" line,
with a warning icon and an explanation whenever PHP is doing the sending. You do not have to
remember to check this file.

#### Enabling X-Sendfile on Apache or LiteSpeed

Apache needs [`mod_xsendfile`](https://github.com/nmaier/mod_xsendfile) installed and enabled, and
a directive allowing it to serve your storage directory:

```apache
XSendFile On
XSendFilePath /home/projectsend/storage/app/files
```

LiteSpeed and OpenLiteSpeed read the same header without an extra module; enable it in the server
configuration.

Then tell ProjectSend to use it, in `.env`:

```dotenv
PROJECTSEND_FILE_DELIVERY=xsendfile
```

**ProjectSend will not switch this on by itself**, even when it can see the module is loaded,
because it cannot see whether `XSendFilePath` allows the storage directory. Guessing wrong there
produces empty downloads rather than slow ones, and an empty download is a much worse failure than
a slow one — so this stays something you turn on having configured it.

#### Choosing explicitly

`PROJECTSEND_FILE_DELIVERY` accepts:

| Value | Meaning |
|---|---|
| `auto` | The default. nginx if the server says it is nginx, PHP otherwise |
| `nginx` | Always `X-Accel-Redirect`. Use this if nginx is proxying another server |
| `xsendfile` | Always `X-Sendfile`, for Apache with `mod_xsendfile`, or LiteSpeed |
| `php` | Always PHP. Correct and slow, and never wrong |

The one case `auto` gets wrong is **nginx reverse-proxying Apache**: PHP is talking to Apache, so
it picks PHP streaming, and downloads work but do not use the nginx in front. Set
`PROJECTSEND_FILE_DELIVERY=nginx` and make sure the front nginx serves `/protected-files/`. While
you are there, give the proxy some header headroom — the same headroom the reference configuration
in Step 6 gives PHP-FPM, in the directives a proxy uses instead:

```nginx
proxy_buffer_size       32k;
proxy_buffers           8 32k;
proxy_busy_buffers_size 64k;
```

nginx buffers a response's headers into a single block that defaults to one memory page — 4 KB on
most systems — and answers `502 Bad Gateway` with `upstream sent too big header` when they do not
fit. The page that goes over is not always the same one, so it presents as an intermittent fault
rather than as a misconfiguration. This applies to any proxy in front of ProjectSend, not just
this one: Nginx Proxy Manager, Traefik and a hand-written nginx vhost all ship the same default.
([#1664](https://github.com/projectsend/projectsend/issues/1664))

#### Or take your server out of it entirely

Store your files in object storage — S3-compatible or Google Cloud Storage (see
[Storing files somewhere other than this server](#storing-files-somewhere-other-than-this-server)).
Files kept there are never on your server's disk, so downloads become a signed, expiring redirect
to the storage provider and the web server is not involved at all. Decide this before people start
uploading, not after.

---

## Step 1 — Put the files on the server

Download `projectsend-x.y.z.zip` from the
[releases page](https://github.com/projectsend/projectsend/releases), upload it to your server, and
unpack it where you want the site to live:

```sh
cd /var/www
unzip projectsend-2.0.0.zip -d projectsend
cd projectsend
```

The release zip is ready to run — you do **not** need Composer, Node, or npm. Everything the app
needs is already inside it.

> **Important:** point your web server at the `public/` folder inside this directory, never at the
> directory itself. Everything above `public/` — your configuration, your database credentials,
> your users' uploaded files — is meant to be unreachable from the web. Step 6 covers this.

## Step 2 — Create the database

Create an empty database and a user that owns it. From the MySQL shell:

```sql
CREATE DATABASE projectsend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'projectsend'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON projectsend.* TO 'projectsend'@'localhost';
FLUSH PRIVILEGES;
```

Leave the database empty. ProjectSend creates its own tables in step 5.

## Step 3 — Tell ProjectSend about your server

Copy the example configuration and open it in an editor:

```sh
cp .env.example .env
```

`.env` is a plain list of `NAME=value` lines. The example file is written for the Docker setup, so
there is a fair amount to change. Here is a complete, working configuration for a manual install —
paste it over the top of the file, then change the addresses and passwords to yours:

```ini
APP_NAME="ProjectSend"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://files.example.com
APP_TIMEZONE=UTC

PROJECTSEND_EDITION=community

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=projectsend
DB_USERNAME=projectsend
DB_PASSWORD=a-long-random-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
```

The four that actually matter:

- **`APP_URL`** — the address people will type to reach your site, including `https://`. Links in
  emails are built from this, so getting it wrong means broken links in every notification.
- **`APP_DEBUG=false`** — leave it off. With debug on, an error page will show visitors parts of
  your configuration.
- **`DB_*`** — what you created in step 2.
- **`APP_KEY`** — leave it empty for now; step 5 fills it in.

The three `database` lines mean sessions, cache and background jobs all live in MySQL, so there is
nothing else to install. If you have Redis, see [Redis](#redis) — but do the install first.

If your site is served over HTTPS, also add:

```ini
SESSION_SECURE_COOKIE=true
```

And if there is a proxy, load balancer or CDN (Cloudflare, an nginx in front of another nginx)
between your visitors and this server, add `TRUSTED_PROXIES` too — the `.env.example` file explains
the format. Without it every visitor appears to come from the proxy, which breaks per-visitor rate
limiting and makes the download log useless.

You can ignore the mail settings for now — email is configured from inside the app once you are
logged in. See [Sending email](#sending-email).

## Step 4 — Set the file permissions

ProjectSend writes to two folders: `storage/` (uploaded files, logs, sessions, cache) and
`bootstrap/cache/`. Both need to be writable by the user your web server runs as — usually
`www-data`, sometimes `nginx`.

```sh
sudo chown -R www-data:www-data /var/www/projectsend
sudo chmod -R 775 /var/www/projectsend/storage /var/www/projectsend/bootstrap/cache
```

### If your web server and PHP-FPM are different users

Check before you go further, because the symptom is misleading:

```sh
ps -o user= -C nginx | sort -u          # the web server's user
ps -o user= -C php-fpm | sort -u        # PHP's user
```

Most servers you set up yourself run both as `www-data` and there is nothing to do here. Managed
panels often do not — cPanel and Plesk commonly give each site its own PHP user while nginx runs as
its own. If the two differ, add this to your `.env`:

```dotenv
FILES_WEB_SERVER_READABLE=true
```

Uploaded files are written `0600` inside `0700` directories, readable only by the user that wrote
them. That is deliberate, and on a same-user server it is the safer setting. But a download is not
served by PHP on nginx: PHP checks permissions and then hands the web server the path with
`X-Accel-Redirect` (see [How downloads are sent](#how-downloads-are-sent)), so the web server has
to open a file PHP owns. When it cannot, **the whole site works and only downloads fail** — the
browser reports `ERR_INVALID_RESPONSE` and the nginx error log says:

```
open() ".../storage/app/files/..." failed (13: Permission denied)
```

The setting relaxes new uploads to `0644`/`0755`. Be aware of what that means on a shared machine:
those modes are readable by *every* account on the server, not only by the web server. The files stay
off the web — the `internal` directive in Step 6 sees to that — but they are no longer private from
your neighbours, so leave this off unless you need it.

Files already on disk keep the permissions they were written with, so fix those once:

```sh
sudo find /var/www/projectsend/storage/app/files -type d -exec chmod 755 {} +
sudo find /var/www/projectsend/storage/app/files -type f -exec chmod 644 {} +
```

**Then check that new uploads keep it.** Upload a file and look at the directory it landed in:

```sh
ls -ld /var/www/projectsend/storage/app/files/*/*
```

If it is `drwxr-xr-x` you are done. If it is still `drwx------`, your PHP-FPM pool runs with a
restrictive umask, and no application setting can beat it: ProjectSend asks for `0755`, but the
directory is created by `mkdir()`, and `mkdir()` masks whatever mode it is given with the umask of
the process. (Files are unaffected — they are set explicitly after being written, so they are `0644`
either way.) Fix it in the pool configuration, not here:

```ini
; /etc/php/8.4/fpm/pool.d/your-pool.conf  — the path varies by panel
php_admin_value[umask] = 0022
```

Some panels expose this as a "umask" field instead. Restart PHP-FPM afterwards, then re-run the
`chmod` above for anything uploaded in the meantime.

## Step 5 — Prepare the application

Three commands. Run them from the install directory, as the web server's user, so that everything
they create ends up with the right owner:

```sh
sudo -u www-data php artisan key:generate
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link
```

What they do: the first generates the secret key used to encrypt sessions and cookies (it writes
itself into your `.env`); the second creates all the database tables; the third makes public assets
like your logo reachable from the web.

> Keep a copy of `APP_KEY` with your backups. Anything encrypted with it — including saved
> credentials for your mail server — cannot be read back without it.

## Step 6 — Point your web server at it

A complete nginx server block. Change `server_name`, and change `/var/www/projectsend` to wherever
you unpacked the files (there are **three** places, including one inside `/protected-files/`):

```nginx
server {
    listen 80;
    server_name files.example.com;
    root /var/www/projectsend/public;
    index index.php;

    client_max_body_size 100m;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # How downloads are served: ProjectSend checks permissions, then asks
    # nginx to send the file. This block must NOT be reachable directly —
    # "internal" is what guarantees that, so do not remove it.
    location /protected-files/ {
        internal;
        alias /var/www/projectsend/storage/app/files/;

        add_header X-Content-Type-Options "nosniff" always;
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        add_header Content-Security-Policy "sandbox; default-src 'none'" always;
    }

    location ~ \.php$ {
        try_files $uri =404;

        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 8 32k;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Then check your PHP settings. Large uploads are sent in 20 MB pieces, so PHP never has to handle a
whole 5 GB file at once — but the pieces still need room. In your `php.ini`:

```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
```

Reload both services:

```sh
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl restart php8.4-fpm
```

Set up HTTPS while you are here — [certbot](https://certbot.eff.org/) issues a free certificate and
edits the nginx config for you.

## Step 7 — Create your administrator

Open your site in a browser. Because no account exists yet, every address takes you to the setup
screen, which asks for a site name and the name, email and password of the first administrator.
Fill it in, and you are done. The first time you sign in, ProjectSend opens on a short list of the
things worth doing first — adding a client, uploading a file, choosing how your file lists and your
email look — each one linking straight to the screen that does it. It appears once; afterwards it
lives at **About → Getting started**.

If you would rather not do it in the browser (or you are scripting the install), the same thing
from the command line:

```sh
sudo -u www-data php artisan projectsend:admin
```

---

## Two things to finish

The app runs without these, but parts of it will quietly not work. Both take a minute.

### The background worker

Sending email and building zip archives of multiple files happen in the background, so nobody sits
watching a spinner. Something has to actually run that work. Create
`/etc/systemd/system/projectsend-worker.service`:

```ini
[Unit]
Description=ProjectSend queue worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/projectsend
ExecStart=/usr/bin/php artisan queue:work --queue=default,zips --tries=3 --backoff=3

[Install]
WantedBy=multi-user.target
```

Then:

```sh
sudo systemctl enable --now projectsend-worker
```

`--queue=default,zips` matters. Building a zip runs on its own queue, so a worker that is not told
to watch `zips` will send email happily and never finish a single zip download — with nothing in any
log to say why. One worker watching both is fine for most installations; ordinary work is taken
first, and a large zip simply holds the worker while it runs.

If zip downloads are heavily used and you would rather they never delayed email, run a second unit
with `--queue=zips` and narrow the first one to `--queue=default`. That is what the Docker images
do.

**Without this, no email is ever sent** and zip downloads never finish. `Restart=always` matters
too: saving your email settings restarts the worker so it picks up the new values, and it needs to
come back on its own.

### The scheduled tasks

A handful of daily housekeeping jobs — deleting expired files, cleaning up abandoned uploads,
checking for new ProjectSend versions. Add one line to the web user's crontab
(`sudo crontab -u www-data -e`):

```cron
* * * * * cd /var/www/projectsend && php artisan schedule:run >> /dev/null 2>&1
```

Yes, every minute. ProjectSend decides internally what is actually due; the cron entry just gives
it a heartbeat.

To check that both of these are working, log in and open **System → Settings → Scheduler**.
It lists every task, when it last ran and whether it succeeded — along with any background job that
failed. If the page says nothing has ever run, your cron line is not firing.

---

## Optional extras

### Sending email

Log in, go to **System → Settings → Email**, and enter your SMTP server's details there. That is
the place to configure it — the settings screen also has a "send test email" button, which will
save you a lot of guessing. The `MAIL_*` values in `.env` are only used until you fill that screen
in.

### Redis

If you have Redis available, it is faster than the database for sessions, cache and queues. Install
the `redis` PHP extension and change three lines in `.env`:

```ini
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Restart PHP-FPM and the background worker afterwards.

Add `REDIS_HOST`, `REDIS_PORT` and `REDIS_PASSWORD` if it is not a default local install. Restart
the worker afterwards.

### Storing files somewhere other than this server

Out of the box, uploads live in `storage/app/files/` on this machine. You can point ProjectSend at
object storage instead from **System → Settings → Storage** — useful when the files outgrow the
server's disk.

Two backends are offered. **S3-compatible** covers AWS S3 and everything speaking that API: MinIO,
Backblaze B2, Wasabi, DigitalOcean Spaces. Leave the endpoint blank for AWS itself, or set it to the
service's own address and turn on path-style addressing, which most of them need. **Google Cloud
Storage** takes a service account key with read and write access to the bucket, pasted in as the JSON
file Google issues; it is stored encrypted and never shown again.

Whichever you choose, use **Test connection** before switching uploads over — it checks the
credentials actually reach the bucket, rather than leaving you to find out at the first upload.

The setting applies to new uploads. Files already on local disk stay there and keep working, and
there is no migration between backends.

### Making it faster

For a busy install, let PHP pre-compile the app's routes and views:

```sh
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

You only run these once: `projectsend:update` notices they are in place and rebuilds them for you
after every update. If you change your mind, `php artisan optimize:clear` undoes all three.

#### `config:cache` and your `.env`

Every Laravel deployment guide also lists `php artisan config:cache`, and `php artisan optimize`
runs it for you. It is safe here, with one thing to remember.

Caching the configuration writes every resolved setting into one PHP file, and from then on the
framework stops reading your `.env` at all — everything in it has already been baked in. So
**re-run `php artisan config:cache` every time you edit `.env`**, or the edit does nothing and you
are left staring at a setting that is plainly there and plainly ignored. `php artisan config:clear`
goes back to reading `.env` directly, and every update clears it too, saying why.

---

## Updating to a new version

```sh
cd /var/www/projectsend
sudo ./update.sh
```

The script ships with every release, in this directory. It asks whether to check GitHub for a newer
version, whether to download it (checking the published checksum), and whether you have a backup —
then takes the site down, unpacks the release, migrates the database, rebuilds whichever caches you
were using, reloads PHP-FPM, restarts the worker and brings the site back up. Your `.env`, your
uploads and your `public/storage` link are never touched.

`sudo ./update.sh --zip ~/projectsend-2.1.0.zip` applies a zip you downloaded yourself, and
`./update.sh --check` just reports what is available.

**[UPDATE.md](UPDATE.md)** is the full reference: what it does in order, every option, the same
steps done by hand, how to check it worked, and how to go back. Read it before your first update —
particularly the part about reloading PHP-FPM, which is the step that decides whether an update
takes effect at all.

Back up first, every time. The script can dump the database for you (`--backup`), but your uploaded
files in `storage/app/files/` are yours to look after.

---

## When something goes wrong

**A page that says "ProjectSend is not configured yet."**
This is not an error — it is ProjectSend telling you which setup step is still missing. You reached
the site before creating your `.env` (step 3) or before generating `APP_KEY` (step 5). The page
names the exact command to run; do that, then reload.

**Every page is blank, or shows a 500 error.**
Look in `storage/logs/` — open the newest file, the real error is at the bottom. Nine times out of ten it is
folder permissions (step 4) or a wrong database password (step 3).

**"Please provide a valid cache path" or "failed to open stream".**
`storage/` or `bootstrap/cache/` is not writable by the web server user. Step 4.

**`php artisan` says "Table 'sessions' doesn't exist".**
Something reached the database before `migrate` created its tables. Finish step 5 in order —
`key:generate`, then `migrate`, then `storage:link`.

**Every address redirects me to the setup screen.**
That is correct behaviour until the first administrator exists. Finish step 7. If you have already
created one and it still happens, ProjectSend cannot reach your database — check `storage/logs/`.

**Pages load but downloads give a 404, or download a 0-byte file.**
On nginx, the `/protected-files/` block is missing from your config, or its `alias` path does not
match where you installed ProjectSend. It must point at `storage/app/files/` and end with a slash.

On any server, check the "Downloads sent by" line in the dashboard's System panel against the
server you are actually running. A 0-byte download means ProjectSend sent a header the server did
not act on — most often `PROJECTSEND_FILE_DELIVERY` set to `nginx` or `xsendfile` on a server that
is neither, or set to `xsendfile` without `XSendFilePath` allowing the storage directory. Setting
`PROJECTSEND_FILE_DELIVERY=php` always works and is the quickest way to confirm that is the
problem. See [How downloads are sent](#how-downloads-are-sent).

**Uploads fail partway through.**
`client_max_body_size` in nginx, or `upload_max_filesize` / `post_max_size` in `php.ini`, is
smaller than a 20 MB upload piece. Step 6.

**No email arrives, and the test email button says it worked.**
"It worked" means it was queued, not delivered. The background worker is not running — see
[The background worker](#the-background-worker).

**The CAPTCHA is stopping people signing in, and I cannot get in to switch it off.**
It should not be able to: a wrong secret key or an unreachable provider both let people through and
report the problem on the settings screen instead. If you are stuck anyway, add
`PROJECTSEND_CAPTCHA_DISABLED=true` to `.env` and run `php artisan optimize:clear`, or run
`php artisan projectsend:captcha-off`. Your keys are kept either way. To check a key without
locking anything, `php artisan projectsend:captcha-test` asks the provider directly.

**Links and redirects drop the port number** (you are served on `:8080`, and the site sends you to
port 80).
Recent Debian and Ubuntu nginx packages set `HTTP_HOST` to `$host` in `/etc/nginx/fastcgi_params`,
deliberately — it stops a client-supplied `Host` header reaching the application — and `$host`
carries no port. On 80 or 443 that changes nothing. On any other port, every absolute URL the
application builds loses it. Add this to the `location ~ \.php$` block, **after** `include
fastcgi_params;`:

```nginx
fastcgi_param HTTP_HOST $http_host;
```

On nginx 1.30 and later the safer form is `$host$is_request_port$request_port`. Either way this only
applies to a non-standard port; behind a TLS proxy on 443 you do not need it.

**Emails and links point at `localhost` or the wrong domain.**
`APP_URL` in `.env`. Fix it and run `php artisan optimize:clear`.

**A change I made in `.env` has no effect.**
Run `php artisan optimize:clear`, then restart PHP-FPM and the worker. Both hold the old values
until they are restarted. If it *still* has no effect, someone has run `php artisan config:cache`
(or `optimize`) on this install — re-run it to pick the new value up, or `php artisan config:clear`
to go back to reading `.env` directly.

**Everyone is locked out of the login form at once, or the download log shows the same IP for
every download.**
ProjectSend is seeing your proxy or CDN instead of your visitors. Set `TRUSTED_PROXIES` in `.env`
(step 3) and restart PHP-FPM.

**Behind a reverse proxy: 419 "page expired" when you log in or save a form, or you land back on
the login screen at random.**
`TRUSTED_PROXIES` again (step 3). Without it ProjectSend never learns the proxy terminated TLS, so
it builds its links and redirects with `http://` while the browser is on `https://`, and marks the
session cookie as non-secure. The browser then declines to send that cookie back, the session
arrives empty, and the write fails with a 419 that reads as an expired session. Make sure your
proxy passes the original `Host` header through as well — `proxy_set_header Host $host;` in nginx,
`passHostHeader=true` in Traefik (its default).

Still stuck? Ask in the [community forum](https://www.projectsend.org/) or open an issue on
[GitHub](https://github.com/projectsend/projectsend/issues), and include the last few lines of
the newest file in `storage/logs/` — it is almost always the fastest way to an answer.
