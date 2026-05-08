#!/bin/bash
set -e

# Clear and warm up cache
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Start Apache
exec "$@"