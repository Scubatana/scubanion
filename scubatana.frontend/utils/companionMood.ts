import type { Companion } from "./types";

export const LOW_HEALTH = 40;

export const LOW_MOOD = 30;

export const HIGH_MOOD = 70;

export function companionBubble(companion: Pick<Companion, "mood" | "health">): string {
	if (companion.health < LOW_HEALTH) {
		return "Je me sens tout mou… On marche un peu chaque jour ?";
	}

	if (companion.mood >= HIGH_MOOD) {
		return "Hey, on sort ? 🌊";
	}

	if (companion.mood >= LOW_MOOD) {
		return "On fait un tour ? J'ai besoin de bouger.";
	}

	return "Je m'ennuie… On sort marcher ?";
}
