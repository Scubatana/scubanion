#include <unity.h>

#include <string>

#include "messages.h"

void setUp() {}

void tearDown() {}

void test_topics_follow_the_protocol()
{
	TEST_ASSERT_EQUAL_STRING("scubanion/bornes/chateau-01/etat", messages::topic("chateau-01", "etat").c_str());
	TEST_ASSERT_EQUAL_STRING("scubanion/bornes/chateau-01/affichage", messages::topic("chateau-01", "affichage").c_str());
}

void test_the_heartbeat_gives_the_firmware_version()
{
	TEST_ASSERT_EQUAL_STRING("{\"status\":\"online\",\"firmware\":\"0.2.0\"}", messages::online("0.2.0").c_str());
}

void test_the_last_will_says_offline()
{
	TEST_ASSERT_EQUAL_STRING("{\"status\":\"offline\"}", messages::offline().c_str());
}

void test_a_display_from_the_api_is_read()
{
	messages::Display display;

	TEST_ASSERT_TRUE(messages::parseDisplay(
		"{\"scanned_at\":\"2026-10-13T15:00:00+02:00\",\"status\":\"rewarded\",\"message\":\"Bravo Léa ! +100 XP\",\"reward_xp\":100}",
		display));
	TEST_ASSERT_EQUAL_INT64(1791896400, display.scannedAt);
	TEST_ASSERT_EQUAL_STRING("rewarded", display.status.c_str());
	TEST_ASSERT_EQUAL_STRING("Bravo Léa ! +100 XP", display.message.c_str());
	TEST_ASSERT_EQUAL_INT(100, display.rewardXp);
}

void test_a_display_without_reward_has_zero_xp()
{
	messages::Display display;

	TEST_ASSERT_TRUE(messages::parseDisplay(
		"{\"scanned_at\":\"2026-10-13T15:00:00+02:00\",\"status\":\"already_rewarded\",\"message\":\"Ton passage du jour est déjà compté, Léa. Reviens demain !\"}",
		display));
	TEST_ASSERT_EQUAL_STRING("already_rewarded", display.status.c_str());
	TEST_ASSERT_EQUAL_INT(0, display.rewardXp);
}

void test_an_unreadable_display_is_refused()
{
	messages::Display display;

	TEST_ASSERT_FALSE(messages::parseDisplay("pas du json", display));
	TEST_ASSERT_FALSE(messages::parseDisplay("{\"status\":\"rewarded\",\"message\":\"Bravo\"}", display));
	TEST_ASSERT_FALSE(messages::parseDisplay("{\"scanned_at\":\"hier\",\"status\":\"rewarded\",\"message\":\"Bravo\"}", display));
	TEST_ASSERT_FALSE(messages::parseDisplay("{\"scanned_at\":\"2026-10-13T15:00:00+02:00\",\"status\":\"rewarded\"}", display));
}

void test_iso_times_are_read_with_their_offset()
{
	int64_t epoch = 0;

	TEST_ASSERT_TRUE(messages::parseIsoTime("2026-10-13T13:00:00Z", epoch));
	TEST_ASSERT_EQUAL_INT64(1791896400, epoch);
	TEST_ASSERT_TRUE(messages::parseIsoTime("2026-11-13T17:59:30-03:30", epoch));
	TEST_ASSERT_EQUAL_INT64(1794605370, epoch);
	TEST_ASSERT_TRUE(messages::parseIsoTime("2000-02-29T00:00:00+00:00", epoch));
	TEST_ASSERT_EQUAL_INT64(951782400, epoch);
}

void test_malformed_iso_times_are_refused()
{
	int64_t epoch = 0;

	TEST_ASSERT_FALSE(messages::parseIsoTime("2026-10-13 15:00:00+02:00", epoch));
	TEST_ASSERT_FALSE(messages::parseIsoTime("2026-13-13T15:00:00+02:00", epoch));
	TEST_ASSERT_FALSE(messages::parseIsoTime("2026-10-13T15:00:00", epoch));
	TEST_ASSERT_FALSE(messages::parseIsoTime("2026-10-13T15:00:00*02:00", epoch));
}

void test_an_old_display_is_not_recent()
{
	messages::Display display;
	display.scannedAt = 1791896400;

	TEST_ASSERT_TRUE(messages::isRecent(display, 1791896400 + 600, 600));
	TEST_ASSERT_FALSE(messages::isRecent(display, 1791896400 + 601, 600));
}

int main()
{
	UNITY_BEGIN();
	RUN_TEST(test_topics_follow_the_protocol);
	RUN_TEST(test_the_heartbeat_gives_the_firmware_version);
	RUN_TEST(test_the_last_will_says_offline);
	RUN_TEST(test_a_display_from_the_api_is_read);
	RUN_TEST(test_a_display_without_reward_has_zero_xp);
	RUN_TEST(test_an_unreadable_display_is_refused);
	RUN_TEST(test_iso_times_are_read_with_their_offset);
	RUN_TEST(test_malformed_iso_times_are_refused);
	RUN_TEST(test_an_old_display_is_not_recent);

	return UNITY_END();
}
