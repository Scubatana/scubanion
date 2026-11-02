#include "token.h"

#include "sha256.h"

namespace token {

std::string generate(const std::string& terminalCode, const std::string& secret, uint64_t scannedAt)
{
	const std::string payload = terminalCode + "." + toBase36(scannedAt);
	uint8_t digest[SHA256_SIZE];

	hmacSha256(
		reinterpret_cast<const uint8_t*>(secret.data()), secret.size(),
		reinterpret_cast<const uint8_t*>(payload.data()), payload.size(),
		digest);

	return payload + "." + base64Url(digest, SIGNATURE_BYTES);
}

std::string toBase36(uint64_t value)
{
	static const char DIGITS[] = "0123456789abcdefghijklmnopqrstuvwxyz";

	if (value == 0) {
		return "0";
	}

	std::string digits;

	while (value > 0) {
		digits.insert(digits.begin(), DIGITS[value % 36]);
		value /= 36;
	}

	return digits;
}

std::string base64Url(const uint8_t* bytes, size_t length)
{
	static const char ALPHABET[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
	std::string encoded;

	for (size_t i = 0; i < length; i += 3) {
		uint32_t chunk = static_cast<uint32_t>(bytes[i]) << 16;

		if (i + 1 < length) {
			chunk |= static_cast<uint32_t>(bytes[i + 1]) << 8;
		}

		if (i + 2 < length) {
			chunk |= bytes[i + 2];
		}

		encoded += ALPHABET[(chunk >> 18) & 63];
		encoded += ALPHABET[(chunk >> 12) & 63];

		if (i + 1 < length) {
			encoded += ALPHABET[(chunk >> 6) & 63];
		}

		if (i + 2 < length) {
			encoded += ALPHABET[chunk & 63];
		}
	}

	return encoded;
}

}
