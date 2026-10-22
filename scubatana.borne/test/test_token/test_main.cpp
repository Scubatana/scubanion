#include <unity.h>

#include <cstring>
#include <string>

#include "sha256.h"
#include "token.h"

namespace {

std::string hex(const uint8_t* bytes, size_t length)
{
	static const char DIGITS[] = "0123456789abcdef";
	std::string out;

	for (size_t i = 0; i < length; i++) {
		out += DIGITS[bytes[i] >> 4];
		out += DIGITS[bytes[i] & 15];
	}

	return out;
}

std::string sha256Hex(const std::string& message)
{
	uint8_t digest[token::SHA256_SIZE];
	token::sha256(reinterpret_cast<const uint8_t*>(message.data()), message.size(), digest);

	return hex(digest, token::SHA256_SIZE);
}

std::string hmacHex(const uint8_t* key, size_t keyLength, const std::string& message)
{
	uint8_t digest[token::SHA256_SIZE];
	token::hmacSha256(key, keyLength, reinterpret_cast<const uint8_t*>(message.data()), message.size(), digest);

	return hex(digest, token::SHA256_SIZE);
}

}

void setUp() {}

void tearDown() {}

void test_sha256_of_a_short_message()
{
	TEST_ASSERT_EQUAL_STRING("ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad", sha256Hex("abc").c_str());
}

void test_sha256_of_a_message_spanning_two_blocks()
{
	TEST_ASSERT_EQUAL_STRING(
		"248d6a61d20638b8e5c026930c3e6039a33ce45964ff2167f6ecedd419db06c1",
		sha256Hex("abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq").c_str());
}

void test_sha256_of_an_empty_message()
{
	TEST_ASSERT_EQUAL_STRING("e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855", sha256Hex("").c_str());
}

void test_hmac_matches_rfc_4231()
{
	const std::string key = "Jefe";

	TEST_ASSERT_EQUAL_STRING(
		"5bdcc146bf60754e6a042426089575c75a003f089d2739839dec58b964ec3843",
		hmacHex(reinterpret_cast<const uint8_t*>(key.data()), key.size(), "what do ya want for nothing?").c_str());
}

void test_hmac_hashes_a_key_longer_than_a_block()
{
	uint8_t key[131];
	memset(key, 0xaa, sizeof(key));

	TEST_ASSERT_EQUAL_STRING(
		"60e431591ee0b67f0d8a26aacbf5b77f8e0bc6213728c5140546040f0ee37f54",
		hmacHex(key, sizeof(key), "Test Using Larger Than Block-Size Key - Hash Key First").c_str());
}

void test_the_time_is_written_in_lowercase_base_36()
{
	TEST_ASSERT_EQUAL_STRING("tmuk40", token::toBase36(1791896400).c_str());
	TEST_ASSERT_EQUAL_STRING("0", token::toBase36(0).c_str());
	TEST_ASSERT_EQUAL_STRING("z", token::toBase36(35).c_str());
	TEST_ASSERT_EQUAL_STRING("10", token::toBase36(36).c_str());
}

void test_base64_url_has_no_padding_and_no_plus_or_slash()
{
	const uint8_t two[] = {0xfb, 0xff};
	const uint8_t three[] = {0xfb, 0xff, 0xbf};
	const uint8_t one[] = {0x66};

	TEST_ASSERT_EQUAL_STRING("-_8", token::base64Url(two, sizeof(two)).c_str());
	TEST_ASSERT_EQUAL_STRING("-_-_", token::base64Url(three, sizeof(three)).c_str());
	TEST_ASSERT_EQUAL_STRING("Zg", token::base64Url(one, sizeof(one)).c_str());
}

void test_tokens_match_the_reference_tokens_of_the_api()
{
	TEST_ASSERT_EQUAL_STRING("chateau-01.tmuk40.uDW6vj4R1gI5gVbx", token::generate("chateau-01", "secret-de-test-scubanion", 1791896400).c_str());
	TEST_ASSERT_EQUAL_STRING("musee-02.tmuk5p.hZhp5q5RKaRZW-9C", token::generate("musee-02", "un-autre-secret", 1791896461).c_str());
}

void test_a_token_with_the_longest_code_fits_in_40_characters()
{
	TEST_ASSERT_LESS_OR_EQUAL(token::MAX_LENGTH, token::generate("abcdefghijklmnop", "secret", 1791896400).size());
}

int main()
{
	UNITY_BEGIN();
	RUN_TEST(test_sha256_of_a_short_message);
	RUN_TEST(test_sha256_of_a_message_spanning_two_blocks);
	RUN_TEST(test_sha256_of_an_empty_message);
	RUN_TEST(test_hmac_matches_rfc_4231);
	RUN_TEST(test_hmac_hashes_a_key_longer_than_a_block);
	RUN_TEST(test_the_time_is_written_in_lowercase_base_36);
	RUN_TEST(test_base64_url_has_no_padding_and_no_plus_or_slash);
	RUN_TEST(test_tokens_match_the_reference_tokens_of_the_api);
	RUN_TEST(test_a_token_with_the_longest_code_fits_in_40_characters);

	return UNITY_END();
}
