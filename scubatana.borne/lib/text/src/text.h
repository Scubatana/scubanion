#pragma once

#include <functional>
#include <string>
#include <vector>

namespace text {

// Coupe un texte en lignes qui tiennent dans maxWidth, mesurées par width.
// Un « ! », « ? », « : » ou « ; » isolé reste collé au mot d'avant.
std::vector<std::string> wrap(const std::string& text, int maxWidth, const std::function<int(const std::string&)>& width);

}
