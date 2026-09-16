#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ -d "/opt/homebrew/opt/node@23/bin" ]; then
	export PATH="/opt/homebrew/opt/node@23/bin:$PATH"
fi

node_major=$(node -e "console.log(process.versions.node.split('.')[0])")
if [ "$node_major" -lt 22 ]; then
	echo "Node >= 22 requis pour Capacitor (trouve : $(node -v)). Installer node 22+ ou l'exposer dans le PATH."
	exit 1
fi

if ! command -v adb >/dev/null 2>&1; then
	echo "adb introuvable, installer les Android platform-tools et l'ajouter au PATH."
	exit 1
fi

if [ ! -d "android" ]; then
	echo "Le projet Android n'existe pas encore. Lancer d'abord : npx cap add android"
	exit 1
fi

device_count=$(adb devices | grep -w "device" | wc -l | tr -d ' ')

if [ "$device_count" -eq 0 ]; then
	echo "Aucun telephone connecte via adb (verifier le debogage USB)."
	exit 1
fi

if [ "$device_count" -gt 1 ]; then
	echo "Plusieurs telephones connectes, impossible de choisir automatiquement lequel utiliser."
	adb devices
	exit 1
fi

if [ -f ".env" ]; then
	set -a
	source .env
	set +a
fi

npm run cap:sync

cd android
./gradlew assembleRelease
cd ..

apk="android/app/build/outputs/apk/release/app-release.apk"

if [ ! -f "$apk" ]; then
	echo "APK release introuvable a $apk apres le build."
	exit 1
fi

adb install -r "$apk"
echo "App mise a jour sur le telephone connecte (donnees locales conservees)."
