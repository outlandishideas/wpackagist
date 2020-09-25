#!/bin/bash
# Ensure the default web server user `www-data` can also write info (e.g.
# IP rate limiting data) to SQLite. Permit failures because another process
# could be performing operations including deletes in parallel.
chown -R "$(whoami)" "${PACKAGE_PATH}" || true
chgrp -R www-data "${PACKAGE_PATH}" || true
find "${PACKAGE_PATH}" -type d -exec chmod 775 {} + || true
find "${PACKAGE_PATH}" -type f -exec chmod 664 {} + || true

echo "Starting refresh..."
php bin/console refresh
echo "Starting update..."
php bin/console update
echo "Starting (force) build..."
php bin/console build --force

echo "Done!"
