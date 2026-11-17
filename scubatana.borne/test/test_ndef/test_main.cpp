#include <unity.h>

#include <string>
#include <vector>

#include "ndef.h"

void setUp() {}

void tearDown() {}

void test_a_borne_address_becomes_a_type_5_tag_with_one_uri_record()
{
	const std::string token = "chateau-01.tmuk40.AAAAAAAAAAAAAAAA";
	std::vector<uint8_t> expected = {
		0xE1, 0x40, 0x40, 0x01,
		0x03, 4 + 1 + 20 + 34,
		0xD1, 0x01, 1 + 20 + 34, 'U', 0x04,
	};
	const std::string rest = "scuba.picsou06.fr/b/" + token;
	expected.insert(expected.end(), rest.begin(), rest.end());
	expected.push_back(0xFE);

	const std::vector<uint8_t> tag = ndef::uriTag("https://scuba.picsou06.fr/b/" + token);

	TEST_ASSERT_EQUAL_size_t(expected.size(), tag.size());
	TEST_ASSERT_EQUAL_UINT8_ARRAY(expected.data(), tag.data(), expected.size());
}

void test_the_longest_prefix_is_used()
{
	TEST_ASSERT_EQUAL_UINT8(0x02, ndef::uriTag("https://www.exemple.fr")[10]);
	TEST_ASSERT_EQUAL_UINT8(0x03, ndef::uriTag("http://exemple.fr")[10]);
	TEST_ASSERT_EQUAL_UINT8(0x00, ndef::uriTag("scubanion://b/x")[10]);
}

void test_an_address_too_long_for_a_short_record_gives_nothing()
{
	TEST_ASSERT_EQUAL_size_t(0, ndef::uriTag("https://" + std::string(250, 'a')).size());
	TEST_ASSERT_EQUAL_size_t(4 + 2 + 254 + 1, ndef::uriTag("https://" + std::string(249, 'a')).size());
}

int main()
{
	UNITY_BEGIN();
	RUN_TEST(test_a_borne_address_becomes_a_type_5_tag_with_one_uri_record);
	RUN_TEST(test_the_longest_prefix_is_used);
	RUN_TEST(test_an_address_too_long_for_a_short_record_gives_nothing);

	return UNITY_END();
}
