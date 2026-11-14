#!/bin/sh
# Lance Mosquitto. Si le certificat du serveur est monté dans /certificat, ajoute un listener TLS sur 8883
# et recharge le certificat quand il est renouvelé (voir docs/mqtt.md, « Chiffrement TLS »).
set -eu

SOURCE_CERT="/certificat/${MQTT_TLS_CERT:-fullchain.pem}"
SOURCE_KEY="/certificat/${MQTT_TLS_KEY:-privkey.pem}"
TLS_DIR=/mosquitto/tls
TLS_CONF=/mosquitto/config/conf.d/tls.conf
CHECK_SECONDS="${MQTT_TLS_CHECK_SECONDS:-3600}"

mkdir -p "$TLS_DIR" /mosquitto/config/conf.d
rm -f "$TLS_CONF"

if [ ! -r "$SOURCE_CERT" ] || [ ! -r "$SOURCE_KEY" ]; then
	echo "Pas de certificat dans /certificat : pas de listener TLS, MQTT seulement sur 1883."
	exec /usr/sbin/mosquitto -c /mosquitto/config/mosquitto.conf
fi

# La clé de Let's Encrypt n'est lisible que par root, et Mosquitto la lit sous l'utilisateur mosquitto :
# on en garde une copie qui lui appartient.
copy_certificate() {
	cp "$SOURCE_CERT" "$TLS_DIR/fullchain.pem"
	cp "$SOURCE_KEY" "$TLS_DIR/privkey.pem"
	chown mosquitto:mosquitto "$TLS_DIR/fullchain.pem" "$TLS_DIR/privkey.pem"
	chmod 644 "$TLS_DIR/fullchain.pem"
	chmod 600 "$TLS_DIR/privkey.pem"
}

fingerprint() {
	cat "$SOURCE_CERT" "$SOURCE_KEY" | md5sum
}

copy_certificate

cat > "$TLS_CONF" <<CONF
listener 8883 0.0.0.0
certfile $TLS_DIR/fullchain.pem
keyfile $TLS_DIR/privkey.pem
tls_version tlsv1.2
CONF

/usr/sbin/mosquitto -c /mosquitto/config/mosquitto.conf &
broker=$!
sleeper=

stop() {
	kill -TERM "$broker" 2>/dev/null || true
	[ -n "$sleeper" ] && kill "$sleeper" 2>/dev/null || true
	wait "$broker" || true
	exit 0
}

trap stop TERM INT
# Relaie la relecture des comptes (docker compose kill -s HUP mosquitto, après bornes:creer) :
# ce script est le PID 1 du conteneur, le signal arrive ici et pas à Mosquitto.
trap 'kill -HUP "$broker" 2>/dev/null || true' HUP

current=$(fingerprint)
elapsed=0

# Toutes les 10 s : Mosquitto tourne-t-il encore ? S'il s'arrête, le script sort avec son code et Docker relance le conteneur.
# Toutes les CHECK_SECONDS : le certificat a-t-il changé ? Mosquitto le relit sur SIGHUP, sans couper les connexions.
while kill -0 "$broker" 2>/dev/null; do
	sleep 10 &
	sleeper=$!
	wait "$sleeper" || true
	sleeper=
	elapsed=$((elapsed + 10))

	if [ "$elapsed" -lt "$CHECK_SECONDS" ]; then
		continue
	fi

	elapsed=0
	latest=$(fingerprint 2>/dev/null || echo "$current")

	if [ "$latest" != "$current" ]; then
		copy_certificate
		kill -HUP "$broker"
		current=$latest
		echo "Certificat renouvelé : rechargé par Mosquitto."
	fi
done

wait "$broker"
