#!/bin/sh
set -e

# First-boot bootstrap for the official image. Adapted from
# docker/app/entrypoint.sh, with the differences that matter in production:
#
#   - The database is external and may not be up yet, so migrations wait
#     for it instead of failing the container into a restart loop.
#   - APP_KEY is generated once and persisted, because a key that changes
#     between restarts invalidates every session and makes every encrypted
#     column unreadable.
#   - Nothing is bind-mounted, so there is no uid juggling to do.
#
# Deliberately NOT run: `config:cache`. It stops .env from being read, which
# silently disables TRUSTED_PROXIES — every visitor then appears to come
# from the proxy, the login rate limiter treats all users as one attacker,
# and the download log records the wrong address. See INSTALL.md.

cd /var/www/html

# storage/ is the declared volume, so anything that must outlive the
# container lives there. Recreate the tree first: a bind-mounted host
# directory arrives empty, unlike a named volume which Docker seeds from
# the image.
mkdir -p storage/app/files \
         storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs
chown -R www-data:www-data storage

# .env is kept on that volume and symlinked into place, so a generated
# APP_KEY survives container replacement. A key that changes between
# restarts invalidates every session and makes every encrypted column
# permanently unreadable — silently, since nothing errors at boot.
#
# Operators who set APP_KEY (and the rest) in the environment need none of
# this: Laravel reads the environment directly and it wins over .env.
if [ ! -f storage/.env ]; then
    cp .env.example storage/.env
    chown www-data:www-data storage/.env
fi
#
# The symlink is owned by the runtime user, not by root who creates it:
# fs.protected_symlinks lets a process follow a symlink in a world-writable
# sticky directory only when it owns the link (or the directory). The image
# no longer leaves /var/www/html in that state, so this is the second lock
# on the same door — cheap, and it is what keeps the failure from coming
# back silently if that directory's mode ever drifts.
[ -L .env ] || ln -sf storage/.env .env
chown -h www-data:www-data .env

if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' storage/.env; then
    echo "projectsend: generating APP_KEY (first boot, persisted to the storage volume)"
    su-exec www-data php artisan key:generate --force --no-interaction
fi

# Wait for the database. `migrate` against a database still starting up is
# the single most common first-run failure, and a bare failure here would
# restart-loop the container with a stack trace instead of a clear message.
if [ "$1" = "supervisord" ] || [ "$1" = "/usr/bin/supervisord" ]; then
    i=0
    until su-exec www-data php artisan db:show --quiet >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "projectsend: database unreachable after 60s — check DB_HOST, DB_DATABASE and credentials" >&2
            exit 1
        fi
        [ "$i" = 1 ] && echo "projectsend: waiting for the database..."
        sleep 1
    done

    # One command, shared with update.sh and with the dev image: migrate,
    # ensure the roles, relink storage, clear the compiled caches, restart
    # the workers, record the version applied. See UpdateInstallation for
    # why the order is what it is, and why maintenance mode is not part of
    # it (`artisan down` would write its flag onto this container's
    # persisted storage volume and outlive the container that wrote it).
    su-exec www-data php artisan projectsend:update

    # Provisioning defaults, before the account they govern exists. A
    # policy an operator wants on from the start has to be written in this
    # boot or not at all: the only other writer is whoever administers the
    # installation, and the line below is what creates them. Seeds only a
    # setting nobody has ever stored, so it never argues with an
    # administrator who changed it later.
    su-exec www-data php artisan projectsend:seed-settings

    # Unattended provisioning: create the first administrator from the
    # environment. Without these, the web setup screen prompts instead.
    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        su-exec www-data php artisan projectsend:admin --if-none \
            --name="${ADMIN_NAME:-Administrator}" \
            --email="$ADMIN_EMAIL" \
            --password="$ADMIN_PASSWORD"
    fi
fi

exec "$@"
