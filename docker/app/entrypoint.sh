#!/bin/sh
set -e

# php-fpm's master process must stay root: it forks workers as www-data
# itself via the pool config (php-fpm can't reopen its own error_log as
# non-root at startup). queue:work/schedule:work have no such fork model,
# so they drop straight to www-data (uid/gid matches the host user, see
# Dockerfile ARGs) instead of running as root for their whole lifetime.

# The app writes to these as www-data, and boot is the one moment this
# container runs as root — so repair ownership here, every boot. Root-run
# `docker compose exec` shells and uid changes across image rebuilds leave
# root-owned directories behind, and the first symptom is an opaque
# "mkdir(): Permission denied" 500 on upload. The file library's contents
# are deliberately NOT chowned recursively: -R over a large library on
# every boot is not free, and new writes only need the directories.
mkdir -p storage/app/files storage/app/private storage/app/public storage/app/uploads-tmp \
    storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views \
    storage/logs bootstrap/cache
chown www-data:www-data storage storage/app storage/app/files storage/app/private storage/app/public
chown -R www-data:www-data storage/app/uploads-tmp storage/framework storage/logs bootstrap/cache

# The worker and the scheduler exec straight into artisan, which cannot run
# without the autoloader — so on a fresh clone, before `composer install`,
# both died instantly and `restart: unless-stopped` brought them back to die
# again, once a second, forever. The log that was filling up is the same one
# somebody needs to read to find out what to do (#1627).
#
# Exit rather than wait: this container has nothing useful to do until the
# dependencies exist, and compose will keep retrying — but slowly enough to
# leave the log readable, and it recovers on its own the moment they appear.
if [ "$1" != "php-fpm" ] && [ ! -f vendor/autoload.php ]; then
    echo "projectsend: the PHP dependencies are not installed — there is no vendor/autoload.php." >&2
    echo "projectsend: run 'docker compose exec app composer install' (see CONTRIBUTING.md)." >&2
    sleep 15

    exit 1
fi

# First-boot bootstrap runs only for the FPM service (the worker execs
# straight through) and only when the app is actually installed.
if [ "$1" = "php-fpm" ] && [ -f vendor/autoload.php ]; then
    # The same command the official image and update.sh run — migrate,
    # roles, storage link, compiled caches, workers. See
    # UpdateInstallation; there is deliberately only one definition of it.
    su-exec www-data php artisan projectsend:update

    # Unattended provisioning: create the first administrator from the
    # environment. Without these, the web setup screen prompts instead.
    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        su-exec www-data php artisan projectsend:admin --if-none \
            --name="${ADMIN_NAME:-Administrator}" \
            --email="$ADMIN_EMAIL" \
            --password="$ADMIN_PASSWORD"
    fi
fi

if [ "$1" = "php-fpm" ]; then
    exec "$@"
else
    exec su-exec www-data "$@"
fi
