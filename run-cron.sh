#!/bin/bash
# Example cron script. The actual cron script on wpackagist.org is more complex, and involves ensuring generated files have the correct permissions.
php bin/console migrate
php bin/console refresh
php bin/console update
php bin/console build
