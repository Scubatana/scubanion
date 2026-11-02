#pragma once

#include <cstddef>
#include <cstdint>
#include <string>

namespace token {

constexpr size_t SIGNATURE_BYTES = 12;

constexpr size_t MAX_LENGTH = 40;

std::string generate(const std::string& terminalCode, const std::string& secret, uint64_t scannedAt);

std::string toBase36(uint64_t value);

std::string base64Url(const uint8_t* bytes, size_t length);

}
