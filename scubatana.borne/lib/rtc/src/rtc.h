#pragma once

#include <array>
#include <cstdint>

// Horloge DS3231 de la borne : conversion entre ses registres (en BCD) et l'heure Unix, en UTC.
// Code pur, sans I2C, testé sur le poste (pio test -e native). L'horloge couvre les années 2000 à 2199.
namespace rtc {

// Registres 0x00 à 0x06 : secondes, minutes, heures, jour de la semaine, date, mois (avec le bit du siècle), année.
using Registers = std::array<uint8_t, 7>;

constexpr uint8_t TIME_REGISTER = 0x00;

// Registre d'état : son bit OSF dit que l'oscillateur s'est arrêté (pile absente ou vide) et que l'heure est perdue.
constexpr uint8_t STATUS_REGISTER = 0x0F;

constexpr uint8_t OSCILLATOR_STOPPED = 0x80;

// Heure Unix lue dans les registres. Faux si un registre est hors bornes (horloge jamais réglée, lecture abîmée).
bool toUnix(const Registers& registers, uint64_t& seconds);

// Registres à écrire pour régler l'horloge à cette heure Unix : mode 24 h, lundi = 1.
Registers fromUnix(uint64_t seconds);

// L'heure de l'horloge est fiable si son oscillateur ne s'est pas arrêté depuis le dernier réglage.
bool isTrustworthy(uint8_t status);

// Registre d'état à réécrire après un réglage : bit OSF effacé, les autres gardés.
uint8_t clearedStatus(uint8_t status);

}
