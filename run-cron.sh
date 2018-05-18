#!/bin/bash
# Example cron script. The actual cron script on wpackagist.org is more complex, and involves ensuring generated files have the correct permissions.
php bin/cmd refresh
php bin/cmd update
php bin/cmd build
