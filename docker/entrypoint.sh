#!/bin/sh
set -e

# Writable directories vtiger needs at runtime (likely mounted as volumes)
for dir in cache logs storage test; do
    mkdir -p "/var/www/html/$dir"
    chown -R www-data:www-data "/var/www/html/$dir"
done

exec "$@"
