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

EXPOSE 80

# Laravel's health route (bootstrap/app.php: health: '/up'). Hitting it
# through nginx rather than php directly means a dead web tier fails the
# check too, not just a dead interpreter.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["projectsend-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
