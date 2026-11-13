#include <Arduino.h>
#include <MQTT.h>
#include <WiFi.h>
#include <time.h>

#include "messages.h"
#include "token.h"

#if __has_include("borne_config.h")
#include "borne_config.h"
#else
#warning "include/borne_config.h absent : la configuration d'exemple est utilisée"
#include "borne_config.example.h"
#endif

namespace {

constexpr const char* FIRMWARE_VERSION = "0.2.0";

constexpr time_t EARLIEST_VALID_TIME = 1767225600;

constexpr unsigned long TOKEN_REFRESH_MS = 5UL * 60 * 1000;

constexpr unsigned long WAITING_MESSAGE_MS = 5000;

constexpr unsigned long HEARTBEAT_MS = 30UL * 1000;

constexpr unsigned long MQTT_RETRY_MS = 5000;

constexpr int MQTT_KEEP_ALIVE_SECONDS = 15;

constexpr int QOS_AT_LEAST_ONCE = 1;

// Le jeton a été écrit au plus 5 minutes avant le scan : au-delà de 10 minutes,
// la réponse vient d'un téléphone qui a envoyé son jeton plus tard, hors ligne.
constexpr int64_t DISPLAY_MAX_AGE_SECONDS = 10 * 60;

const std::string STATE_TOPIC = messages::topic(BORNE_CODE, "etat");

const std::string DISPLAY_TOPIC = messages::topic(BORNE_CODE, "affichage");

const std::string OFFLINE_MESSAGE = messages::offline();

const std::string MQTT_ACCOUNT_PASSWORD = token::mqttPassword(BORNE_SECRET);

WiFiClient network;

MQTTClient mqtt(512);

bool hasToken = false;

unsigned long lastTokenAt = 0;

unsigned long lastWaitingMessageAt = 0;

bool mqttAttempted = false;

unsigned long lastMqttAttemptAt = 0;

unsigned long lastHeartbeatAt = 0;

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

// Appelé pendant mqtt.loop() : ne pas publier ici.
void showDisplay(String& topic, String& payload)
{
	messages::Display display;

	if (!messages::parseDisplay(std::string(payload.c_str(), payload.length()), display)) {
		Serial.println("Affichage illisible, ignoré");
		return;
	}

	if (clockIsSet() && !messages::isRecent(display, time(nullptr), DISPLAY_MAX_AGE_SECONDS)) {
		Serial.println("Affichage d'un scan trop ancien, ignoré");
		return;
	}

	Serial.printf("Affichage (%s) : %s\n", display.status.c_str(), display.message.c_str());
}

void publishOnline()
{
	mqtt.publish(STATE_TOPIC.c_str(), messages::online(FIRMWARE_VERSION).c_str(), true, QOS_AT_LEAST_ONCE);
	lastHeartbeatAt = millis();
}

void connectMqtt()
{
	if (mqttAttempted && millis() - lastMqttAttemptAt < MQTT_RETRY_MS) {
		return;
	}

	mqttAttempted = true;
	lastMqttAttemptAt = millis();

	if (!mqtt.connect(BORNE_CODE, BORNE_CODE, MQTT_ACCOUNT_PASSWORD.c_str())) {
		Serial.printf("Connexion au broker MQTT %s:%d refusée ou impossible (erreur %d, code %d)\n",
			MQTT_HOST, MQTT_PORT, static_cast<int>(mqtt.lastError()), static_cast<int>(mqtt.returnCode()));
		return;
	}

	Serial.println("Connectée au broker MQTT");
	mqtt.subscribe(DISPLAY_TOPIC.c_str(), QOS_AT_LEAST_ONCE);
	publishOnline();
}

void keepMqttConnected()
{
	if (WiFi.status() != WL_CONNECTED) {
		return;
	}

	if (!mqtt.connected()) {
		connectMqtt();
		return;
	}

	mqtt.loop();

	if (millis() - lastHeartbeatAt >= HEARTBEAT_MS) {
		publishOnline();
	}
}

}

void setup()
{
	Serial.begin(115200);
	Serial.printf("Borne %s, firmware %s\n", BORNE_CODE, FIRMWARE_VERSION);

	WiFi.mode(WIFI_STA);
	WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
	configTime(0, 0, NTP_SERVER);

	mqtt.begin(MQTT_HOST, MQTT_PORT, network);
	mqtt.setKeepAlive(MQTT_KEEP_ALIVE_SECONDS);
	mqtt.setCleanSession(false);
	mqtt.setWill(STATE_TOPIC.c_str(), OFFLINE_MESSAGE.c_str(), true, QOS_AT_LEAST_ONCE);
	mqtt.onMessage(showDisplay);
}

void loop()
{
	keepMqttConnected();

	if (!clockIsSet()) {
		waitForClock();
		return;
	}

	if (!hasToken || millis() - lastTokenAt >= TOKEN_REFRESH_MS) {
		writeToken();
	}
}
