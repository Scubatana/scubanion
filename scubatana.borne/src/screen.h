#pragma once

#include "messages.h"

// Écran ST7789 de la carte (320 × 240 en paysage).
namespace screen {

void begin();

void showIdle(bool online);

// À appeler à chaque tour de boucle : fait flotter le logo tant que l'accueil est affiché.
void animate();

void showDisplay(const messages::Display& display);

}
