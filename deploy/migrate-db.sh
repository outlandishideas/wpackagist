echo "Running DB migrations..."
bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=$APP_ENV
