#include <unity.h>

#include "rtc.h"

void setUp() {}

void tearDown() {}

void test_the_registers_of_a_set_clock_give_the_unix_time()
{
	// Mardi 17/11/2026, 15:04:05 UTC.
	const rtc::Registers registers = {0x05, 0x04, 0x15, 0x02, 0x17, 0x11, 0x26};
	uint64_t seconds = 0;

	TEST_ASSERT_TRUE(rtc::toUnix(registers, seconds));
	TEST_ASSERT_EQUAL_UINT64(1794927845ULL, seconds);
}

void test_setting_the_clock_writes_bcd_registers_in_24_hour_mode()
{
	const rtc::Registers expected = {0x05, 0x04, 0x15, 0x02, 0x17, 0x11, 0x26};
	const rtc::Registers registers = rtc::fromUnix(1794927845ULL);

	TEST_ASSERT_EQUAL_UINT8_ARRAY(expected.data(), registers.data(), expected.size());
}

void test_a_time_survives_a_round_trip_through_the_registers()
{
	// 01/01/2000, 01/01/2026 (heure minimale admise par la borne), 29/02/2028, 31/12/2099, 01/01/2100.
	const uint64_t times[] = {946684800ULL, 1767225600ULL, 1835481599ULL, 4102444799ULL, 4102444800ULL};

	for (const uint64_t time : times) {
		uint64_t back = 0;

		TEST_ASSERT_TRUE(rtc::toUnix(rtc::fromUnix(time), back));
		TEST_ASSERT_EQUAL_UINT64(time, back);
	}
}

void test_the_century_bit_and_the_weekday_follow_the_date()
{
	const rtc::Registers lastDay = rtc::fromUnix(4102444799ULL);
	const rtc::Registers firstDay = rtc::fromUnix(4102444800ULL);

	TEST_ASSERT_EQUAL_HEX8(0x12, lastDay[5]);
	TEST_ASSERT_EQUAL_HEX8(0x99, lastDay[6]);
	TEST_ASSERT_EQUAL_UINT8(4, lastDay[3]);
	TEST_ASSERT_EQUAL_HEX8(0x81, firstDay[5]);
	TEST_ASSERT_EQUAL_HEX8(0x00, firstDay[6]);
	TEST_ASSERT_EQUAL_UINT8(5, firstDay[3]);
}

void test_a_clock_left_in_12_hour_mode_is_still_read()
{
	uint64_t seconds = 0;

	// 3 h de l'après-midi, minuit (12 AM) et midi (12 PM).
	TEST_ASSERT_TRUE(rtc::toUnix({0x05, 0x04, 0x63, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_EQUAL_UINT64(1794927845ULL, seconds);
	TEST_ASSERT_TRUE(rtc::toUnix({0x00, 0x00, 0x52, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_EQUAL_UINT64(1794873600ULL, seconds);
	TEST_ASSERT_TRUE(rtc::toUnix({0x00, 0x00, 0x72, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_EQUAL_UINT64(1794916800ULL, seconds);
}

void test_impossible_registers_are_refused()
{
	uint64_t seconds = 0;

	TEST_ASSERT_FALSE(rtc::toUnix({0x5A, 0x04, 0x15, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_FALSE(rtc::toUnix({0x60, 0x04, 0x15, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_FALSE(rtc::toUnix({0x05, 0x04, 0x24, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_FALSE(rtc::toUnix({0x05, 0x04, 0x15, 0x02, 0x00, 0x11, 0x26}, seconds));
	TEST_ASSERT_FALSE(rtc::toUnix({0x05, 0x04, 0x15, 0x02, 0x17, 0x13, 0x26}, seconds));
	TEST_ASSERT_FALSE(rtc::toUnix({0x05, 0x04, 0x15, 0x02, 0x29, 0x02, 0x26}, seconds));
	TEST_ASSERT_FALSE(rtc::toUnix({0x05, 0x04, 0x40, 0x02, 0x17, 0x11, 0x26}, seconds));
	TEST_ASSERT_TRUE(rtc::toUnix({0x05, 0x04, 0x15, 0x02, 0x29, 0x02, 0x28}, seconds));
}

void test_a_stopped_oscillator_means_the_time_is_lost()
{
	TEST_ASSERT_TRUE(rtc::isTrustworthy(0x08));
	TEST_ASSERT_FALSE(rtc::isTrustworthy(0x88));
	TEST_ASSERT_EQUAL_HEX8(0x08, rtc::clearedStatus(0x88));
	TEST_ASSERT_EQUAL_HEX8(0x0B, rtc::clearedStatus(0x8B));
}

int main()
{
	UNITY_BEGIN();
	RUN_TEST(test_the_registers_of_a_set_clock_give_the_unix_time);
	RUN_TEST(test_setting_the_clock_writes_bcd_registers_in_24_hour_mode);
	RUN_TEST(test_a_time_survives_a_round_trip_through_the_registers);
	RUN_TEST(test_the_century_bit_and_the_weekday_follow_the_date);
	RUN_TEST(test_a_clock_left_in_12_hour_mode_is_still_read);
	RUN_TEST(test_impossible_registers_are_refused);
	RUN_TEST(test_a_stopped_oscillator_means_the_time_is_lost);

	return UNITY_END();
}
