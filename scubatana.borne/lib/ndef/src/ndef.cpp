#include "ndef.h"

#include <iterator>

namespace ndef {

namespace {

// Préfixes abrégés par un octet dans un enregistrement URI (NFC Forum, URI Record Type Definition).
struct Prefix {
	uint8_t code;
	const char* text;
};

constexpr Prefix PREFIXES[] = {
	{0x02, "https://www."},
	{0x01, "http://www."},
	{0x04, "https://"},
	{0x03, "http://"},
};

// Type 5, adressage sur un octet, version 1.0, lecture et écriture libres, 512 octets déclarés
// (64 × 8), lecture de plusieurs blocs à la fois. La puce en a plus, mais le jeton tient largement.
constexpr uint8_t CAPABILITY_CONTAINER[] = {0xE1, 0x40, 0x40, 0x01};

constexpr uint8_t NDEF_MESSAGE_TLV = 0x03;
constexpr uint8_t TERMINATOR_TLV = 0xFE;

// Enregistrement unique (MB et ME), court (SR), de type « bien connu » (TNF 1).
constexpr uint8_t RECORD_HEADER = 0xD1;

// Au-delà, la longueur ne tient plus sur un octet (TLV et enregistrement court).
constexpr size_t MAX_LENGTH = 0xFE;

}

std::vector<uint8_t> uriTag(const std::string& uri)
{
	uint8_t prefixCode = 0x00;
	std::string rest = uri;

	for (const Prefix& prefix : PREFIXES) {
		const std::string text = prefix.text;

		if (uri.compare(0, text.size(), text) == 0) {
			prefixCode = prefix.code;
			rest = uri.substr(text.size());
			break;
		}
	}

	const size_t payloadLength = 1 + rest.size();
	const size_t recordLength = 4 + payloadLength;

	if (recordLength > MAX_LENGTH) {
		return {};
	}

	std::vector<uint8_t> tag(std::begin(CAPABILITY_CONTAINER), std::end(CAPABILITY_CONTAINER));
	tag.insert(tag.end(), {NDEF_MESSAGE_TLV, static_cast<uint8_t>(recordLength)});
	tag.insert(tag.end(), {RECORD_HEADER, 0x01, static_cast<uint8_t>(payloadLength), 'U', prefixCode});
	tag.insert(tag.end(), rest.begin(), rest.end());
	tag.push_back(TERMINATOR_TLV);

	return tag;
}

}
