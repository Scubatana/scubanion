#pragma once

// Copier ce fichier en borne_config.h (ignoré par git) puis le remplir.

// Code et secret donnés par « php artisan bornes:creer ». Code de 16 caractères au plus.
#define BORNE_CODE "chateau-01"
#define BORNE_SECRET "a-remplacer"

// Réseau en 2,4 GHz : l'ESP32 ne capte pas le 5 GHz.
#define WIFI_SSID "a-remplacer"
#define WIFI_PASSWORD "a-remplacer"

#define NTP_SERVER "pool.ntp.org"
