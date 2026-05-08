#!/bin/bash
set -e

# Clear and warm up cache
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Start Apache
exec "$@"