#!/bin/sh
set -eu

cd /var/www/html

mkdir -p tmp public/tmp public/uploads

if [ -f composer.json ] && [ ! -f sistema/vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

exec "$@"
