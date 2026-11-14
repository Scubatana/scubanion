#include "screen.h"

#include <Adafruit_ST7789.h>
#include <SPI.h>
#include <U8g2_for_Adafruit_GFX.h>

#include <string>
#include <vector>

#include "logo.h"
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

// Le logo de l'accueil flotte de haut en bas : ±6 px autour de LOGO_TOP, un aller-retour en 3,6 s.
constexpr int LOGO_LEFT = (WIDTH - LOGO_WIDTH) / 2;
constexpr int LOGO_TOP = 28;
constexpr float LOGO_FLOAT_PX = 6;
constexpr unsigned long LOGO_FLOAT_PERIOD_MS = 3600;

// Rétroéclairage piloté en PWM (LEDC de l'ESP32) : plein pendant l'activité, environ 10 % au repos.
constexpr uint8_t BACKLIGHT_CHANNEL = 7;
constexpr uint32_t BACKLIGHT_FREQUENCY = 5000;
constexpr uint8_t BACKLIGHT_RESOLUTION_BITS = 8;
constexpr uint8_t BACKLIGHT_FULL = 255;
constexpr uint8_t BACKLIGHT_RESTING = 26;

SPIClass bus(HSPI);

Adafruit_ST7789 tft(&bus, PIN_CS, PIN_DC, -1);

U8G2_FOR_ADAFRUIT_GFX u8g2;

bool idleShown = false;

bool resting = false;

int logoY = LOGO_TOP;

// Même couleur que BACKGROUND dans tools/logo.py, qui remplace la transparence du logo.
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

int floatingLogoY()
{
	const float phase = static_cast<float>(millis() % LOGO_FLOAT_PERIOD_MS) / LOGO_FLOAT_PERIOD_MS;

	return LOGO_TOP + static_cast<int>(lroundf(LOGO_FLOAT_PX * sinf(2 * PI * phase)));
}

// Version rapide de drawRGBBitmap (celle d'Adafruit_SPITFT, qui envoie les pixels d'un bloc) :
// elle attend un pointeur non constant, mais ne modifie pas l'image.
void drawLogo(int y)
{
	tft.drawRGBBitmap(LOGO_LEFT, y, const_cast<uint16_t*>(LOGO), LOGO_WIDTH, LOGO_HEIGHT);
}

// L'image du logo a déjà le fond de l'accueil : il suffit d'effacer la bande que la nouvelle position ne recouvre pas.
void moveLogo(int y)
{
	drawLogo(y);

	if (y > logoY) {
		tft.fillRect(LOGO_LEFT, logoY, LOGO_WIDTH, y - logoY, idleColor());
	} else {
		tft.fillRect(LOGO_LEFT, y + LOGO_HEIGHT, LOGO_WIDTH, logoY - y, idleColor());
	}

	logoY = y;
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

void begin()
{
	ledcSetup(BACKLIGHT_CHANNEL, BACKLIGHT_FREQUENCY, BACKLIGHT_RESOLUTION_BITS);
	ledcAttachPin(PIN_BACKLIGHT, BACKLIGHT_CHANNEL);
	ledcWrite(BACKLIGHT_CHANNEL, BACKLIGHT_FULL);

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

	logoY = resting ? LOGO_TOP : floatingLogoY();
	drawLogo(logoY);
	idleShown = true;

	u8g2.setFont(u8g2_font_helvR18_tf);
	drawCentered("Pose ton téléphone", 172);
	drawCentered("sur le cercle", 202);

	// Pastille pour l'équipe : verte quand la borne est connectée au broker, rouge sinon.
	tft.fillCircle(MARGIN, HEIGHT - MARGIN, 5, online ? tft.color565(76, 175, 80) : tft.color565(229, 57, 53));
}

void animate()
{
	if (!idleShown || resting) {
		return;
	}

	const int y = floatingLogoY();

	if (y != logoY) {
		moveLogo(y);
	}
}

void rest()
{
	if (resting) {
		return;
	}

	resting = true;
	ledcWrite(BACKLIGHT_CHANNEL, BACKLIGHT_RESTING);

	if (idleShown && logoY != LOGO_TOP) {
		moveLogo(LOGO_TOP);
	}
}

void wake()
{
	if (!resting) {
		return;
	}

	resting = false;
	ledcWrite(BACKLIGHT_CHANNEL, BACKLIGHT_FULL);
}

bool isResting()
{
	return resting;
}

void showDisplay(const messages::Display& display)
{
	wake();
	idleShown = false;

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
