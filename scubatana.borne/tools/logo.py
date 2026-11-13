"""Convertit le logo de l'app en image RGB565 pour l'écran de la borne.

    pip install pillow
    python tools/logo.py ../scubatana.frontend/public/img/logo.png src/logo.h

La transparence est remplacée par la couleur de fond de l'écran d'accueil
(idleColor() dans src/screen.cpp) : à garder identiques.
"""

import sys

from PIL import Image

HEIGHT = 100
BACKGROUND = (16, 42, 67)


def rgb565(red, green, blue):
	return ((red & 0xF8) << 8) | ((green & 0xFC) << 3) | (blue >> 3)


def main(source, target):
	logo = Image.open(source).convert("RGBA")
	width = round(logo.width * HEIGHT / logo.height)
	logo = logo.resize((width, HEIGHT), Image.LANCZOS)

	image = Image.new("RGBA", logo.size, BACKGROUND + (255,))
	image.alpha_composite(logo)
	data = image.convert("RGB").tobytes()
	pixels = [rgb565(data[i], data[i + 1], data[i + 2]) for i in range(0, len(data), 3)]

	lines = [
		"#pragma once",
		"",
		"// Généré par tools/logo.py depuis le logo de l'app : ne pas modifier à la main.",
		"",
		"#include <pgmspace.h>",
		"#include <stdint.h>",
		"",
		f"constexpr int16_t LOGO_WIDTH = {width};",
		f"constexpr int16_t LOGO_HEIGHT = {HEIGHT};",
		"",
		"const uint16_t LOGO[] PROGMEM = {",
	]

	for start in range(0, len(pixels), 12):
		lines.append("\t" + ", ".join(f"0x{value:04X}" for value in pixels[start:start + 12]) + ",")

	lines.append("};")

	with open(target, "w", encoding="utf-8", newline="\n") as file:
		file.write("\n".join(lines) + "\n")


if __name__ == "__main__":
	main(sys.argv[1], sys.argv[2])
