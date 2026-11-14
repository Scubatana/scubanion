#pragma once

// Copier ce fichier en borne_config.h (ignoré par git) puis le remplir.

// Code et secret donnés par « php artisan bornes:creer ». Code de 16 caractères au plus.
#define BORNE_CODE "chateau-01"
#define BORNE_SECRET "a-remplacer"

// Réseau en 2,4 GHz : l'ESP32 ne capte pas le 5 GHz.
#define WIFI_SSID "a-remplacer"
#define WIFI_PASSWORD "a-remplacer"

#define NTP_SERVER "pool.ntp.org"

// Broker MQTT (voir docs/mqtt.md). En dev, l'adresse IP du PC qui fait tourner docker compose.
#define MQTT_HOST "scuba.picsou06.fr"

// Chiffrement TLS sur le port 8883 : la borne vérifie le certificat du broker avec les autorités de
// Let's Encrypt (include/autorites_racines.h). Pour un broker de test signé par sa propre autorité,
// définir MQTT_CA_CERT avec le PEM de cette autorité. MQTT_TLS à false et port 1883 : en clair, dev seulement.
#define MQTT_TLS true
#define MQTT_PORT 8883
// #define MQTT_CA_CERT "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----\n"

// Début de l'adresse écrite dans le tag NFC, suivie du jeton (voir docs/mqtt.md).
#define NFC_URL_PREFIX "https://scuba.picsou06.fr/b/"
