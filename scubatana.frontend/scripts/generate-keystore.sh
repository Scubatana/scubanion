#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ -f ".env" ]; then
	set -a
	source .env
	set +a
fi

: "${ANDROID_KEYSTORE_PATH:?definir ANDROID_KEYSTORE_PATH dans .env}"
: "${ANDROID_KEY_ALIAS:?definir ANDROID_KEY_ALIAS dans .env}"
: "${ANDROID_KEYSTORE_PASSWORD:?definir ANDROID_KEYSTORE_PASSWORD dans .env}"

if [ -f "$ANDROID_KEYSTORE_PATH" ]; then
	echo "Keystore deja present a $ANDROID_KEYSTORE_PATH, rien a faire."
	echo "Pour en regenerer un (invalide toutes les mises a jour existantes), supprimer le fichier d'abord."
	exit 0
fi

mkdir -p "$(dirname "$ANDROID_KEYSTORE_PATH")"

keytool -genkeypair \
	-keystore "$ANDROID_KEYSTORE_PATH" \
	-alias "$ANDROID_KEY_ALIAS" \
	-keyalg RSA \
	-keysize 2048 \
	-validity 10000 \
	-storepass "$ANDROID_KEYSTORE_PASSWORD" \
	-dname "CN=Scubanion, OU=IUT, O=Scubanion, C=FR"

echo "Keystore genere a $ANDROID_KEYSTORE_PATH."
echo "Cle de dev versionnee dans le depot (voir .gitignore) : le mot de passe dans .env reste local, non versionne."
