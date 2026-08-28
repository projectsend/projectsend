# The official ProjectSend image — Community edition.
#
# This does NOT build the application. It packages a release artifact that
# has already been built and verified, so the image and the published zip
# are the same bytes. That is also what keeps it buildable without any
# credentials: the artifact arrives with its dependencies already vendored,
# so nothing here needs to reach the network.
#
# The build context is a staging directory, not the repository: unpack a
# release zip, place these files beside it, and build from there.
#
# One container runs the whole application: nginx, php-fpm, the queue
# worker and the scheduler, under supervisord. ProjectSend needs nginx
# specifically — protected downloads are served with X-Accel-Redirect, so
# an fpm-only image would leave every download broken unless the operator
# reproduced the config exactly. An external MySQL and Redis are still
# expected; see compose.example.yaml.

FROM php:8.4-fpm-alpine

LABEL org.opencontainers.image.title="ProjectSend" \
      org.opencontainers.image.description="Client file sharing, self-hosted. Community edition." \
      org.opencontainers.image.source="https://github.com/projectsend/projectsend" \
      org.opencontainers.image.licenses="MIT"

# Extension set is identical to docker/app/Dockerfile — if that one gains an
# extension because the application started needing it, this one has to gain
# it too, or the image boots and then fails at the first request that uses it.
RUN apk add --no-cache \
        nginx \
        supervisor \
        su-exec \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        linux-headers \
        # LDAP links at runtime, so unlike $PHPIZE_DEPS it must survive the
        # cleanup below. Required in every edition: directorytree/ldaprecord
        # declares ext-ldap, so the app will not boot without it whether or
        # not this installation ever binds to a directory.
        openldap-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        pdo_mysql \
        intl \
        zip \
        gd \
        ldap \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

# A fixed uid, unlike the dev image's WWWUSER/WWWGROUP build args. Those
# exist only to make a bind-mounted repo writable without chown; there are
# no bind mounts here, and a published image must not vary by build host.
RUN delgroup www-data 2>/dev/null || true \
    && deluser www-data 2>/dev/null || true \
    && addgroup -g 1000 www-data \
    && adduser -D -H -u 1000 -G www-data www-data

# nginx must run as that same user, and the alpine package does not: it
# ships `user nginx;` (uid 100) in its own nginx.conf. The two identities
# have to match because php-fpm hands nginx files to serve — protected
# downloads and thumbnails go out by X-Accel-Redirect — and the
# application creates directories on demand at Flysystem's private mode,
# 0700. Traversing one of those means *being* its owner; there are no
# group or other bits to fall back on, so a differently-owned nginx worker
# answers 403 on the first thumbnail it is asked for.
#
# Changing the directive is only half of it. /var/lib/nginx and its tmp/
# come from the package owned by the old user, and a worker cannot reach
# the temp directories underneath a parent it may not traverse — nginx
# recreates the leaves at boot as the new user, so they look right while
# every request nginx buffers to disk (every upload chunk, which is what
# client_max_body_size is set high for) fails with a bare 500 that never
# reaches php-fpm.
RUN sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf \
    && chown -R www-data:www-data /var/lib/nginx

# nginx is the one process here that does not log where supervisord can
# see it. supervisord captures what a program writes to its own stdout,
# but nginx opens the files named in the package's nginx.conf the moment
# it reads its config, so its access and error logs went to
# /var/log/nginx/ inside the container — invisible to `docker logs`, which
# is the only place anybody looks. That is where the reason for every 502
# and every 403 was being written, so a reverse-proxy problem presented as
# no logs at all on either side. Point both at the container's own
# streams, which is what the rest of this image already does.
RUN sed -i -e 's#^error_log .*#error_log /dev/stderr warn;#' \
           -e 's#^\(\s*\)access_log .*#\1access_log /dev/stdout main;#' \
           /etc/nginx/nginx.conf

COPY docker/production/php.ini        /usr/local/etc/php/conf.d/projectsend.ini
COPY docker/production/www-pool.conf  /usr/local/etc/php-fpm.d/zz-www-pool.conf
COPY docker/production/nginx.conf     /etc/nginx/http.d/default.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh  /usr/local/bin/projectsend-entrypoint
RUN chmod +x /usr/local/bin/projectsend-entrypoint

WORKDIR /var/www/html

# The verified release artifact, already unpacked by build-image.sh. It
# arrives with vendor/ and public/build/ built — no composer or npm here.
COPY --chown=www-data:www-data app/ /var/www/html/

# storage/ and bootstrap/cache must be writable by the runtime user.
#
# /var/www/html itself needs re-owning as well, and it is easy to miss: COPY
# --chown re-owns what it copies *into* the directory, never the directory,
# so it keeps what the base image gave it — uid 82 (the www-data this image
# replaced above) and mode 1777, which php:*-fpm sets so that an image can
# run as an arbitrary user. Left that way, the directory is world-writable,
# sticky, and owned by nobody the container knows about, and the kernel's
# fs.protected_symlinks (on by default on Ubuntu, Debian and most current
# distributions) then refuses to let the php-fpm worker follow the .env
# symlink the entrypoint puts there. Root is exempt, so `docker exec ... cat
# .env` reads it back perfectly while every real request 503s with
# "ProjectSend is not configured yet".
RUN mkdir -p storage/app/files storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX storage bootstrap/cache \
    && chown www-data:www-data /var/www/html \
    && chmod 755 /var/www/html \
    && mkdir -p /run/nginx

# The whole of storage/, not just storage/app/files. Uploaded files are the
# obvious thing to persist, but .env lives here too (the entrypoint puts it
# there and symlinks it into place) so that a generated APP_KEY survives
# container replacement — a key that changes silently invalidates every
# session and makes every encrypted column unreadable.
VOLUME ["/var/www/html/storage"]

# How the application knows it is this image and not a container somebody
# built from a checkout — the two upgrade completely differently, and it is
# the application that prints the instructions. See Installation. It has to
# be set here rather than inferred at runtime: an operator who bind-mounts
# over /var/www/html can hide any file the image ships as its evidence, and
# an environment variable survives that.
ENV PROJECTSEND_IMAGE=1

# This is a production image, so it says so itself.
#
# The entrypoint seeds storage/.env from .env.example on first boot when no
# .env exists yet, and .env.example is the development template:
# APP_ENV=local, APP_DEBUG=true. compose.example.yaml sets both correctly,
# so the documented way to run this was never affected -- but `docker run`
# with nothing but a database address, a Portainer/unRAID/TrueNAS template,
# or a Kubernetes manifest naming only DB/Redis/APP_URL, all quietly got a
# debug build.
#
# Two consequences an operator would not expect and cannot see from the
# outside:
#
#   - APP_DEBUG=true renders Laravel's exception page -- stack trace,
#     file, surrounding source -- to whoever triggered the 500, signed in
#     or not. php.ini's display_errors=Off does not prevent it: Laravel
#     renders that page itself.
#   - PasswordPolicy appends ->uncompromised() only when
#     app()->isProduction(), so on APP_ENV=local an administrator's
#     "reject known-breached passwords" never ran, while descriptor() went
#     on advertising it and the security settings screen went on showing
#     it as active.
#
# Set here rather than in the seeded .env so that an installation already
# running on a stale .env is fixed by pulling the image, not only a fresh
# one.
#
# What this does and does not outrank. Laravel builds its env repository
# immutable (Illuminate\Support\Env), so a real environment variable wins
# over the .env file. `docker run -e`, compose `environment:` and a
# Kubernetes `env:` all set real environment variables and therefore still
# win over these -- an operator who asks for something explicitly gets it.
# Editing APP_ENV or APP_DEBUG *inside* storage/.env no longer takes
# effect, because these are real environment variables and that file is
# not; `-e APP_DEBUG=true` is the way to turn debug on deliberately.
ENV APP_ENV=production APP_DEBUG=false

EXPOSE 80

# Laravel's health route (bootstrap/app.php: health: '/up'). Hitting it
# through nginx rather than php directly means a dead web tier fails the
# check too, not just a dead interpreter.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["projectsend-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
