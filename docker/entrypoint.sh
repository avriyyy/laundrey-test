#!/bin/sh
set -e

# Laravel caches (safe to rebuild every boot)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Database schema (safe to re-run)
php artisan migrate --force

exec apache2-foreground
