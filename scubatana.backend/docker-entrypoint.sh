#!/bin/sh
set -e

if [ ! -f "composer.json" ]; then
	echo "Laravel n'est pas encore initialise dans scubatana.backend/."
	echo "Lancer une seule fois : docker run --rm -v \"\$PWD/scubatana.backend:/app\" -w /app composer:2 create-project laravel/laravel ."
	exit 1
fi

if [ ! -d "vendor" ]; then
	composer install --no-interaction --prefer-dist
fi

exec "$@"
