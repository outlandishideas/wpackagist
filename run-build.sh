#!/bin/bash
# Ensure the default web server user `www-data` can also write info (e.g.
# IP rate limiting data) to SQLite. Permit failures because another process
# could be performing operations including deletes in parallel.
#
# See if we can get away without this, now that EFS has been set up in each env.
# TODO figure out a more durable solution.
#chown -R "$(whoami)" "${PACKAGE_PATH}" || true
#chgrp -R www-data "${PACKAGE_PATH}" || true
#find "${PACKAGE_PATH}" -type d -exec chmod 775 {} + || true
#find "${PACKAGE_PATH}" -type f -exec chmod 664 {} + || true

echo "Starting build..."
php bin/console build

echo "Done!"
