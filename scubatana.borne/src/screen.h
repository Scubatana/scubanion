#pragma once

#include "messages.h"

// Écran ST7789 de la carte (320 × 240 en paysage).
namespace screen {

void begin(const char* terminalCode);

void showIdle(bool online);

void showDisplay(const messages::Display& display);

}
