#!/usr/bin/env bash

set -e

VERSION=$1

if ! shift; then
    echo "$0: Missing required version parameter." >&2
    exit 1
fi

if [ -z "$VERSION" ]; then
    echo "$0: Empty version parameter." >&2
    exit 1
fi

TAG="v$VERSION"
TARGET="simplesamlphp-$VERSION"

cd /tmp

if [ -a "$TARGET" ]; then
    echo "$0: Destination already exists: $TARGET" >&2
    exit 1
fi

umask 0022

REPOPATH="https://github.com/simplesamlphp/simplesamlphp.git"

git clone $REPOPATH $TARGET
cd $TARGET
git checkout $TAG
cd ..

# Use composer only on newer versions that have a composer.json
if [ -f "$TARGET/composer.json" ]; then
    if [ ! -x "$TARGET/composer.phar" ]; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=$TARGET
    fi

    # Install dependencies (without vcs history or dev tools)
    php "$TARGET/composer.phar" install --no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-infocard:1.0.2 --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-aggregator --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-aggregator2 --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-autotest --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-consentsimpleadmin --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-logpeek --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-metaedit --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-modinfo --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require "openid/php-openid:dev-master#ee669c6a9d4d95b58ecd9b6945627276807694fb" --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-openid --update-no-dev --prefer-dist -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-openidprovider --update-no-dev --prefer-dist -d "$TARGET"
    php "$TARGET/composer.phar" require rediris-es/simplesamlphp-module-papi --update-no-dev --prefer-dist -o -d "$TARGET"
    php "$TARGET/composer.phar" require simplesamlphp/simplesamlphp-module-saml2debug --update-no-dev --prefer-dist -o -d "$TARGET"
fi

for MOD in InfoCard aggregator aggregator2 autotest consentSimpleAdmin logpeek metaedit modinfo openid openidProvider papi saml2debug
do
    mv "$TARGET/modules/$MOD/default-enable" "$TARGET/modules/$MOD/default-disable"
done

mkdir -p "$TARGET/config" "$TARGET/metadata" "$TARGET/cert" "$TARGET/log"
cp -rv "$TARGET/config-templates/"* "$TARGET/config/"
cp -rv "$TARGET/metadata-templates/"* "$TARGET/metadata/"
rm -rf "$TARGET/.git"
rm "$TARGET/.coveralls.yml"
rm "$TARGET/.travis.yml"
rm "$TARGET/.gitignore"
rm "$TARGET/composer.phar"
tar --owner 0 --group 0 -cvzf "$TARGET.tar.gz" "$TARGET"
rm -rf "$TARGET"

echo "Created: /tmp/$TARGET.tar.gz"

