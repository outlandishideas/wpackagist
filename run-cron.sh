#!/bin/bash
php bin/console migrate
php bin/console refresh
php bin/console update
php bin/console build

echo "Done!"
