#include <unity.h>

#include <string>
#include <vector>

#include "text.h"

namespace {

// Une unité par caractère, en comptant les lettres accentuées (2 octets en UTF-8) pour une.
int characters(const std::string& value)
{
	int count = 0;

	for (const char c : value) {
		if ((static_cast<unsigned char>(c) & 0xC0) != 0x80) {
			count++;
		}
	}

	return count;
}

void assertLines(const std::vector<std::string>& expected, const std::vector<std::string>& actual)
{
	TEST_ASSERT_EQUAL_size_t(expected.size(), actual.size());

	for (size_t i = 0; i < expected.size(); i++) {
		TEST_ASSERT_EQUAL_STRING(expected[i].c_str(), actual[i].c_str());
	}
}

}

void setUp() {}

void tearDown() {}

void test_a_short_text_stays_on_one_line()
{
	assertLines({ "Bravo Léa ! +100 XP" }, text::wrap("Bravo Léa ! +100 XP", 19, characters));
}

void test_a_long_text_is_cut_between_words()
{
	assertLines(
		{ "Ton passage du", "jour est déjà", "compté, Léa.", "Reviens demain !" },
		text::wrap("Ton passage du jour est déjà compté, Léa. Reviens demain !", 17, characters));
}

void test_punctuation_stays_with_the_previous_word()
{
	assertLines({ "Bravo Léa !", "+100 XP" }, text::wrap("Bravo Léa ! +100 XP", 10, characters));
}

void test_a_word_too_long_gets_its_own_line()
{
	assertLines({ "Bravo", "Anticonstitutionnellement !", "Salut" }, text::wrap("Bravo Anticonstitutionnellement ! Salut", 10, characters));
}

void test_extra_spaces_are_ignored()
{
	assertLines({ "Bravo Léa" }, text::wrap("  Bravo   Léa  ", 20, characters));
	TEST_ASSERT_EQUAL_size_t(0, text::wrap("", 20, characters).size());
}

int main()
{
	UNITY_BEGIN();
	RUN_TEST(test_a_short_text_stays_on_one_line);
	RUN_TEST(test_a_long_text_is_cut_between_words);
	RUN_TEST(test_punctuation_stays_with_the_previous_word);
	RUN_TEST(test_a_word_too_long_gets_its_own_line);
	RUN_TEST(test_extra_spaces_are_ignored);

	return UNITY_END();
}
