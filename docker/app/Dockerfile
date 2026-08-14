FROM php:8.4-fpm-alpine

# Matches the host user's uid/gid so the bind-mounted repo (dev) needs no
# runtime chown: www-data inside the container owns it already.
ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN apk add --no-cache \
        su-exec \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        linux-headers \
        # LDAP authentication. Kept out of the $PHPIZE_DEPS group below,
        # which is deleted after the build — this one has to stay, since
        # the extension links against it at runtime. Required in every
        # edition, not only where LDAP is switched on: directorytree/
        # ldaprecord declares ext-ldap, so composer install fails without
        # it whether or not an installation ever binds to a directory.
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
    && delgroup www-data 2>/dev/null || true \
    && deluser www-data 2>/dev/null || true \
    && addgroup -g "$WWWGROUP" www-data \
    && adduser -D -H -u "$WWWUSER" -G www-data www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/app/php.ini /usr/local/etc/php/conf.d/projectsend.ini
COPY docker/app/www-pool.conf /usr/local/etc/php-fpm.d/zz-www-pool.conf
COPY docker/app/entrypoint.sh /usr/local/bin/projectsend-entrypoint
RUN chmod +x /usr/local/bin/projectsend-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["projectsend-entrypoint"]
CMD ["php-fpm"]
