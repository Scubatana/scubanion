#include <Arduino.h>
#include <WiFi.h>
#include <time.h>

#include "token.h"

#if __has_include("borne_config.h")
#include "borne_config.h"
#else
#warning "include/borne_config.h absent : la configuration d'exemple est utilisée"
#include "borne_config.example.h"
#endif

namespace {

constexpr const char* FIRMWARE_VERSION = "0.1.0";

constexpr time_t EARLIEST_VALID_TIME = 1767225600;

constexpr unsigned long TOKEN_REFRESH_MS = 5UL * 60 * 1000;

constexpr unsigned long WAITING_MESSAGE_MS = 5000;

bool hasToken = false;

unsigned long lastTokenAt = 0;

unsigned long lastWaitingMessageAt = 0;

bool clockIsSet()
{
	return time(nullptr) > EARLIEST_VALID_TIME;
}

void waitForClock()
{
	if (millis() - lastWaitingMessageAt < WAITING_MESSAGE_MS) {
		return;
	}

	lastWaitingMessageAt = millis();
	Serial.println(WiFi.status() == WL_CONNECTED ? "Heure en cours de synchronisation..." : "En attente du Wi-Fi...");
}

void writeToken()
{
	const std::string scanToken = token::generate(BORNE_CODE, BORNE_SECRET, static_cast<uint64_t>(time(nullptr)));

	Serial.printf("Jeton : %s\n", scanToken.c_str());
	Serial.printf("Adresse du tag : %s%s\n", NFC_URL_PREFIX, scanToken.c_str());

	hasToken = true;
	lastTokenAt = millis();
}

}

void setup()
{
	Serial.begin(115200);
	Serial.printf("Borne %s, firmware %s\n", BORNE_CODE, FIRMWARE_VERSION);

	WiFi.mode(WIFI_STA);
	WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
	configTime(0, 0, NTP_SERVER);
}

void loop()
{
	if (!clockIsSet()) {
		waitForClock();
		return;
	}

	if (!hasToken || millis() - lastTokenAt >= TOKEN_REFRESH_MS) {
		writeToken();
	}
}
