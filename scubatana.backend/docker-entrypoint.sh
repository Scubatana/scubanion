#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
	echo "APP_KEY manquante : la générer avec « php artisan key:generate --show » et la renseigner dans le .env racine." >&2
	exit 1
fi

php artisan optimize

if [ "$RUN_MIGRATIONS" = "true" ]; then
	php artisan migrate --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
