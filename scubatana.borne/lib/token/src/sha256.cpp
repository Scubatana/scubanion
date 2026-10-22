#include "sha256.h"

#include <cstring>

namespace token {

namespace {

constexpr size_t BLOCK_SIZE = 64;

constexpr uint32_t ROUND_CONSTANTS[64] = {
	0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
	0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
	0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
	0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
	0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
	0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
	0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
	0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
};

uint32_t rotateRight(uint32_t value, int bits)
{
	return (value >> bits) | (value << (32 - bits));
}

class Sha256 {
public:
	void update(const uint8_t* data, size_t length)
	{
		for (size_t i = 0; i < length; i++) {
			block[blockLength++] = data[i];

			if (blockLength == BLOCK_SIZE) {
				compress();
				blockLength = 0;
			}
		}

		totalLength += length;
	}

	void finish(uint8_t digest[SHA256_SIZE])
	{
		const uint64_t bits = totalLength * 8;
		const uint8_t marker = 0x80;
		const uint8_t zero = 0;

		update(&marker, 1);

		while (blockLength != BLOCK_SIZE - 8) {
			update(&zero, 1);
		}

		uint8_t length[8];

		for (int i = 0; i < 8; i++) {
			length[i] = static_cast<uint8_t>(bits >> (56 - 8 * i));
		}

		update(length, 8);

		for (int i = 0; i < 8; i++) {
			digest[4 * i] = static_cast<uint8_t>(state[i] >> 24);
			digest[4 * i + 1] = static_cast<uint8_t>(state[i] >> 16);
			digest[4 * i + 2] = static_cast<uint8_t>(state[i] >> 8);
			digest[4 * i + 3] = static_cast<uint8_t>(state[i]);
		}
	}

private:
	uint32_t state[8] = {0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19};
	uint8_t block[BLOCK_SIZE] = {};
	size_t blockLength = 0;
	uint64_t totalLength = 0;

	void compress()
	{
		uint32_t words[64];

		for (int i = 0; i < 16; i++) {
			words[i] = static_cast<uint32_t>(block[4 * i]) << 24
				| static_cast<uint32_t>(block[4 * i + 1]) << 16
				| static_cast<uint32_t>(block[4 * i + 2]) << 8
				| static_cast<uint32_t>(block[4 * i + 3]);
		}

		for (int i = 16; i < 64; i++) {
			const uint32_t s0 = rotateRight(words[i - 15], 7) ^ rotateRight(words[i - 15], 18) ^ (words[i - 15] >> 3);
			const uint32_t s1 = rotateRight(words[i - 2], 17) ^ rotateRight(words[i - 2], 19) ^ (words[i - 2] >> 10);
			words[i] = words[i - 16] + s0 + words[i - 7] + s1;
		}

		uint32_t a = state[0], b = state[1], c = state[2], d = state[3];
		uint32_t e = state[4], f = state[5], g = state[6], h = state[7];

		for (int i = 0; i < 64; i++) {
			const uint32_t s1 = rotateRight(e, 6) ^ rotateRight(e, 11) ^ rotateRight(e, 25);
			const uint32_t choice = (e & f) ^ (~e & g);
			const uint32_t first = h + s1 + choice + ROUND_CONSTANTS[i] + words[i];
			const uint32_t s0 = rotateRight(a, 2) ^ rotateRight(a, 13) ^ rotateRight(a, 22);
			const uint32_t majority = (a & b) ^ (a & c) ^ (b & c);
			const uint32_t second = s0 + majority;

			h = g;
			g = f;
			f = e;
			e = d + first;
			d = c;
			c = b;
			b = a;
			a = first + second;
		}

		state[0] += a;
		state[1] += b;
		state[2] += c;
		state[3] += d;
		state[4] += e;
		state[5] += f;
		state[6] += g;
		state[7] += h;
	}
};

}

void sha256(const uint8_t* data, size_t length, uint8_t digest[SHA256_SIZE])
{
	Sha256 hash;
	hash.update(data, length);
	hash.finish(digest);
}

void hmacSha256(const uint8_t* key, size_t keyLength, const uint8_t* message, size_t messageLength, uint8_t digest[SHA256_SIZE])
{
	uint8_t keyBlock[BLOCK_SIZE] = {};

	if (keyLength > BLOCK_SIZE) {
		sha256(key, keyLength, keyBlock);
	} else {
		memcpy(keyBlock, key, keyLength);
	}

	uint8_t pad[BLOCK_SIZE];
	uint8_t innerDigest[SHA256_SIZE];

	for (size_t i = 0; i < BLOCK_SIZE; i++) {
		pad[i] = keyBlock[i] ^ 0x36;
	}

	Sha256 inner;
	inner.update(pad, BLOCK_SIZE);
	inner.update(message, messageLength);
	inner.finish(innerDigest);

	for (size_t i = 0; i < BLOCK_SIZE; i++) {
		pad[i] = keyBlock[i] ^ 0x5c;
	}

	Sha256 outer;
	outer.update(pad, BLOCK_SIZE);
	outer.update(innerDigest, SHA256_SIZE);
	outer.finish(digest);
}

}
