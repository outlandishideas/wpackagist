#!/bin/bash
php bin/console migrate
php bin/console refresh
php bin/console update
php bin/console build

# Ensure the default web server user `www-data` can also write info (e.g.
# IP rate limiting data) to SQLite.
chmod 0666 "${PACKAGE_PATH}/packages.sqlite"

echo "Done!"
