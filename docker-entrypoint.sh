#!/bin/bash
set -e

# Ensure runtime directories exist with correct permissions
mkdir -p /var/www/html/var/cache/prod/sessions
mkdir -p /var/www/html/var/log
chown -R www-data:www-data /var/www/html/var
chmod -R 775 /var/www/html/var

# Clear and warm up cache
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Start Apache
exec "$@"