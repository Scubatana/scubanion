#pragma once

#include <cstddef>
#include <cstdint>

namespace token {

constexpr size_t SHA256_SIZE = 32;

void sha256(const uint8_t* data, size_t length, uint8_t digest[SHA256_SIZE]);

void hmacSha256(const uint8_t* key, size_t keyLength, const uint8_t* message, size_t messageLength, uint8_t digest[SHA256_SIZE]);

}
