#pragma once

#include <cstdint>
#include <string>
#include <vector>

// Contenu du tag NFC de la borne (NFC Forum Type 5) : l'adresse qui ouvre l'app avec le jeton.
namespace ndef {

// Octets à écrire au début de la mémoire utilisateur : en-tête du tag (Capability Container),
// puis un message NDEF d'un seul enregistrement URI. Vide si l'adresse est trop longue.
std::vector<uint8_t> uriTag(const std::string& uri);

}
