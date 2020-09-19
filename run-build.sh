#!/bin/bash
# Ensure the default web server user `www-data` can also write info (e.g.
# IP rate limiting data) to SQLite.
sudo chown -R "$(whoami)" "${PACKAGE_PATH}"
chgrp -R www-data "${PACKAGE_PATH}"
find "${PACKAGE_PATH}" -type d -exec chmod 775 {} +
find "${PACKAGE_PATH}" -type f -exec chmod 664 {} +

php bin/console build

echo "Done!"
