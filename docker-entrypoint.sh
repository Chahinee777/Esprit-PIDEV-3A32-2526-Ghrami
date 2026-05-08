#!/bin/bash
set -e

# Clear and warm up cache
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# For fresh database - force schema creation instead of migrations
php bin/console doctrine:schema:update --force --complete

# Start Apache
exec "$@"