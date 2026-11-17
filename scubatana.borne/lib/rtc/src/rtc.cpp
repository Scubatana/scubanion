#include "rtc.h"

namespace rtc {

namespace {

constexpr uint64_t SECONDS_PER_DAY = 86400;

constexpr uint8_t TWELVE_HOUR_MODE = 0x40;

constexpr uint8_t AFTERNOON = 0x20;

constexpr uint8_t NEXT_CENTURY = 0x80;

bool fromBcd(uint8_t value, unsigned& decimal)
{
	const unsigned tens = value >> 4;
	const unsigned units = value & 0x0F;

	if (tens > 9 || units > 9) {
		return false;
	}

	decimal = tens * 10 + units;

	return true;
}

uint8_t toBcd(unsigned value)
{
	return static_cast<uint8_t>(((value / 10) << 4) | (value % 10));
}

bool isLeapYear(unsigned year)
{
	return (year % 4 == 0 && year % 100 != 0) || year % 400 == 0;
}

unsigned daysInMonth(unsigned year, unsigned month)
{
	static const unsigned lengths[] = {31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31};

	return month == 2 && isLeapYear(year) ? 29 : lengths[month - 1];
}

// Jours écoulés depuis le 1er janvier 1970, et l'inverse : algorithmes « days from civil » et
// « civil from days » de Howard Hinnant, exacts sur tout le calendrier grégorien.
int64_t daysFromCivil(int64_t year, unsigned month, unsigned day)
{
	year -= month <= 2 ? 1 : 0;
	const int64_t era = (year >= 0 ? year : year - 399) / 400;
	const unsigned yearOfEra = static_cast<unsigned>(year - era * 400);
	const unsigned dayOfYear = (153 * (month > 2 ? month - 3 : month + 9) + 2) / 5 + day - 1;
	const unsigned dayOfEra = yearOfEra * 365 + yearOfEra / 4 - yearOfEra / 100 + dayOfYear;

	return era * 146097 + static_cast<int64_t>(dayOfEra) - 719468;
}

void civilFromDays(int64_t days, int64_t& year, unsigned& month, unsigned& day)
{
	days += 719468;
	const int64_t era = (days >= 0 ? days : days - 146096) / 146097;
	const unsigned dayOfEra = static_cast<unsigned>(days - era * 146097);
	const unsigned yearOfEra = (dayOfEra - dayOfEra / 1460 + dayOfEra / 36524 - dayOfEra / 146096) / 365;
	const unsigned dayOfYear = dayOfEra - (365 * yearOfEra + yearOfEra / 4 - yearOfEra / 100);
	const unsigned monthIndex = (5 * dayOfYear + 2) / 153;

	day = dayOfYear - (153 * monthIndex + 2) / 5 + 1;
	month = monthIndex < 10 ? monthIndex + 3 : monthIndex - 9;
	year = static_cast<int64_t>(yearOfEra) + era * 400 + (month <= 2 ? 1 : 0);
}

bool readHour(uint8_t value, unsigned& hour)
{
	if ((value & TWELVE_HOUR_MODE) == 0) {
		return fromBcd(value & 0x3F, hour);
	}

	unsigned hourOfHalfDay = 0;

	if (!fromBcd(value & 0x1F, hourOfHalfDay) || hourOfHalfDay < 1 || hourOfHalfDay > 12) {
		return false;
	}

	hour = hourOfHalfDay % 12 + ((value & AFTERNOON) != 0 ? 12 : 0);

	return true;
}

}

bool toUnix(const Registers& registers, uint64_t& seconds)
{
	unsigned second = 0;
	unsigned minute = 0;
	unsigned hour = 0;
	unsigned date = 0;
	unsigned month = 0;
	unsigned year = 0;

	if (!fromBcd(registers[0] & 0x7F, second) || !fromBcd(registers[1] & 0x7F, minute) || !readHour(registers[2], hour)
		|| !fromBcd(registers[4] & 0x3F, date) || !fromBcd(registers[5] & 0x1F, month) || !fromBcd(registers[6], year)) {
		return false;
	}

	const unsigned fullYear = 2000 + year + ((registers[5] & NEXT_CENTURY) != 0 ? 100 : 0);

	if (second > 59 || minute > 59 || hour > 23 || month < 1 || month > 12 || date < 1 || date > daysInMonth(fullYear, month)) {
		return false;
	}

	seconds = static_cast<uint64_t>(daysFromCivil(fullYear, month, date)) * SECONDS_PER_DAY + hour * 3600 + minute * 60 + second;

	return true;
}

Registers fromUnix(uint64_t seconds)
{
	const int64_t days = static_cast<int64_t>(seconds / SECONDS_PER_DAY);
	const unsigned secondOfDay = static_cast<unsigned>(seconds % SECONDS_PER_DAY);
	int64_t year = 0;
	unsigned month = 0;
	unsigned day = 0;

	civilFromDays(days, year, month, day);

	// Le 1er janvier 1970 était un jeudi : 4 quand le lundi vaut 1.
	const unsigned weekday = static_cast<unsigned>((days + 3) % 7) + 1;

	return {
		toBcd(secondOfDay % 60),
		toBcd(secondOfDay / 60 % 60),
		toBcd(secondOfDay / 3600),
		static_cast<uint8_t>(weekday),
		toBcd(day),
		static_cast<uint8_t>(toBcd(month) | (year >= 2100 ? NEXT_CENTURY : 0)),
		toBcd(static_cast<unsigned>(year % 100)),
	};
}

bool isTrustworthy(uint8_t status)
{
	return (status & OSCILLATOR_STOPPED) == 0;
}

uint8_t clearedStatus(uint8_t status)
{
	return static_cast<uint8_t>(status & ~OSCILLATOR_STOPPED);
}

}
