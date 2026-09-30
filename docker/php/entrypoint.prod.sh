#!/bin/sh
set -e

# Caches config, routes, views and events. Runs at start, not at build time,
# so it picks up the server's .env.
php artisan optimize

exec "$@"
