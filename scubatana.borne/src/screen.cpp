#include "screen.h"

#include <Adafruit_ST7789.h>
#include <SPI.h>
#include <U8g2_for_Adafruit_GFX.h>

#include <string>
#include <vector>

#include "text.h"

namespace screen {

namespace {

// Brochage de l'écran, fiche LCDwiki de la carte (voir docs/borne-materiel.md).
constexpr int8_t PIN_SCK = 14;
constexpr int8_t PIN_MISO = 12;
constexpr int8_t PIN_MOSI = 13;
constexpr int8_t PIN_CS = 15;
constexpr int8_t PIN_DC = 2;
constexpr int8_t PIN_BACKLIGHT = 21;

constexpr int WIDTH = 320;
constexpr int HEIGHT = 240;
constexpr int MARGIN = 16;
constexpr int LINE_GAP = 8;
constexpr uint32_t SPI_FREQUENCY = 40000000;

SPIClass bus(HSPI);

Adafruit_ST7789 tft(&bus, PIN_CS, PIN_DC, -1);

U8G2_FOR_ADAFRUIT_GFX u8g2;

std::string code;

uint16_t idleColor()
{
	return tft.color565(16, 42, 67);
}

void clear(uint16_t background)
{
	tft.fillScreen(background);
	u8g2.setBackgroundColor(background);
	u8g2.setForegroundColor(ST77XX_WHITE);
}

int lineHeight()
{
	return u8g2.getFontAscent() - u8g2.getFontDescent() + LINE_GAP;
}

void drawCentered(const std::string& line, int baseline)
{
	const int width = u8g2.getUTF8Width(line.c_str());
	u8g2.drawUTF8((WIDTH - width) / 2, baseline, line.c_str());
}

std::vector<std::string> wrapped(const std::string& paragraph)
{
	return text::wrap(paragraph, WIDTH - 2 * MARGIN, [](const std::string& line) {
		return static_cast<int>(u8g2.getUTF8Width(line.c_str()));
	});
}

// Centre verticalement un paragraphe autour de centerY.
void drawParagraph(const std::string& paragraph, const uint8_t* font, int centerY)
{
	u8g2.setFont(font);

	const std::vector<std::string> lines = wrapped(paragraph);
	const int height = static_cast<int>(lines.size()) * lineHeight() - LINE_GAP;
	int baseline = centerY - height / 2 + u8g2.getFontAscent();

	for (const std::string& line : lines) {
		drawCentered(line, baseline);
		baseline += lineHeight();
	}
}

}

void begin(const char* terminalCode)
{
	code = terminalCode;

	pinMode(PIN_BACKLIGHT, OUTPUT);
	digitalWrite(PIN_BACKLIGHT, HIGH);

	bus.begin(PIN_SCK, PIN_MISO, PIN_MOSI, PIN_CS);
	tft.init(240, 320);
	// La bibliothèque active l'inversion des couleurs, utile sur d'autres dalles ST7789 mais pas sur celle-ci.
	tft.invertDisplay(false);
	tft.setSPISpeed(SPI_FREQUENCY);
	tft.setRotation(1);

	u8g2.begin(tft);
	u8g2.setFontMode(1);
}

void showIdle(bool online)
{
	clear(idleColor());

	u8g2.setFont(u8g2_font_helvB24_tf);
	drawCentered("Scubanion", 70);

	u8g2.setFont(u8g2_font_helvR18_tf);
	drawCentered("Pose ton téléphone", 125);
	drawCentered("sur le cercle", 155);

	u8g2.setFont(u8g2_font_helvR10_tf);
	const std::string status = "Borne " + code + (online ? " · en ligne" : " · hors ligne");
	drawCentered(status, HEIGHT - 14);

	tft.fillCircle(MARGIN, HEIGHT - 18, 5, online ? tft.color565(76, 175, 80) : tft.color565(229, 57, 53));
}

void showDisplay(const messages::Display& display)
{
	if (display.status == "rewarded") {
		clear(tft.color565(46, 125, 50));

		u8g2.setFont(u8g2_font_helvB24_tf);
		drawCentered("Bien joué !", 62);

		drawParagraph(display.message, u8g2_font_helvB18_tf, 150);
		return;
	}

	clear(display.status == "already_rewarded" ? tft.color565(239, 108, 0) : idleColor());
	drawParagraph(display.message, u8g2_font_helvB18_tf, HEIGHT / 2);
}

}
