#include "messages.h"

#include <ArduinoJson.h>

namespace messages {

namespace {

constexpr const char* TOPIC_PREFIX = "scubanion/bornes/";

std::string serialize(const JsonDocument& document)
{
	char buffer[128];
	const size_t length = serializeJson(document, buffer, sizeof(buffer));

	return std::string(buffer, length);
}

bool readNumber(const std::string& text, size_t position, size_t digits, int& value)
{
	value = 0;

	for (size_t i = position; i < position + digits; i++) {
		if (text[i] < '0' || text[i] > '9') {
			return false;
		}

		value = value * 10 + (text[i] - '0');
	}

	return true;
}

int64_t daysFromCivil(int year, int month, int day)
{
	year -= month <= 2;
	const int era = (year >= 0 ? year : year - 399) / 400;
	const int yearOfEra = year - era * 400;
	const int dayOfYear = (153 * (month + (month > 2 ? -3 : 9)) + 2) / 5 + day - 1;
	const int dayOfEra = yearOfEra * 365 + yearOfEra / 4 - yearOfEra / 100 + dayOfYear;

	return static_cast<int64_t>(era) * 146097 + dayOfEra - 719468;
}

}

std::string topic(const std::string& terminalCode, const std::string& channel)
{
	return TOPIC_PREFIX + terminalCode + "/" + channel;
}

std::string online(const std::string& firmware)
{
	JsonDocument document;
	document["status"] = "online";
	document["firmware"] = firmware.c_str();

	return serialize(document);
}

std::string offline()
{
	JsonDocument document;
	document["status"] = "offline";

	return serialize(document);
}

bool parseDisplay(const std::string& json, Display& display)
{
	JsonDocument document;

	if (deserializeJson(document, json.data(), json.size()) != DeserializationError::Ok) {
		return false;
	}

	const char* scannedAt = document["scanned_at"];
	const char* status = document["status"];
	const char* message = document["message"];

	if (scannedAt == nullptr || status == nullptr || message == nullptr || !parseIsoTime(scannedAt, display.scannedAt)) {
		return false;
	}

	display.status = status;
	display.message = message;
	display.rewardXp = document["reward_xp"] | 0;

	return true;
}

// Format de toIso8601String() côté API : 2026-10-13T15:00:00+02:00, ou Z pour UTC.
bool parseIsoTime(const std::string& text, int64_t& epoch)
{
	const bool utc = text.size() == 20 && text[19] == 'Z';

	if (!utc && text.size() != 25) {
		return false;
	}

	if (text[4] != '-' || text[7] != '-' || text[10] != 'T' || text[13] != ':' || text[16] != ':') {
		return false;
	}

	int year, month, day, hour, minute, second;

	if (!readNumber(text, 0, 4, year) || !readNumber(text, 5, 2, month) || !readNumber(text, 8, 2, day)
		|| !readNumber(text, 11, 2, hour) || !readNumber(text, 14, 2, minute) || !readNumber(text, 17, 2, second)) {
		return false;
	}

	if (month < 1 || month > 12 || day < 1 || day > 31 || hour > 23 || minute > 59 || second > 59) {
		return false;
	}

	int offsetSeconds = 0;

	if (!utc) {
		int offsetHours, offsetMinutes;

		if ((text[19] != '+' && text[19] != '-') || text[22] != ':' || !readNumber(text, 20, 2, offsetHours) || !readNumber(text, 23, 2, offsetMinutes)) {
			return false;
		}

		offsetSeconds = (offsetHours * 3600 + offsetMinutes * 60) * (text[19] == '-' ? -1 : 1);
	}

	epoch = daysFromCivil(year, month, day) * 86400 + hour * 3600 + minute * 60 + second - offsetSeconds;

	return true;
}

bool isRecent(const Display& display, int64_t now, int64_t maxAgeSeconds)
{
	return now - display.scannedAt <= maxAgeSeconds;
}

}
