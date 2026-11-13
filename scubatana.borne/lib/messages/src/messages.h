#pragma once

#include <cstdint>
#include <string>

// Messages MQTT échangés avec le serveur (voir docs/mqtt.md).
namespace messages {

struct Display {
	int64_t scannedAt = 0;
	std::string status;
	std::string message;
	int rewardXp = 0;
};

std::string topic(const std::string& terminalCode, const std::string& channel);

std::string online(const std::string& firmware);

std::string offline();

bool parseDisplay(const std::string& json, Display& display);

bool parseIsoTime(const std::string& text, int64_t& epoch);

bool isRecent(const Display& display, int64_t now, int64_t maxAgeSeconds);

}
