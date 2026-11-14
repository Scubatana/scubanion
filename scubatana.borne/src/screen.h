#pragma once

#include "messages.h"

// Écran ST7789 de la carte (320 × 240 en paysage).
namespace screen {

void begin();

void showIdle(bool online);

// À appeler à chaque tour de boucle : fait flotter le logo tant que l'accueil est affiché, hors repos.
void animate();

// Repos (sobriété) : rétroéclairage à environ 10 % et logo immobile. Un affichage reçu réveille l'écran.
void rest();

void wake();

bool isResting();

void showDisplay(const messages::Display& display);

}
