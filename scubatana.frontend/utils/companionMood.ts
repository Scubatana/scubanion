import type { Companion } from "./types";

export const LOW_HEALTH = 40;

export const LOW_MOOD = 30;

export const HIGH_MOOD = 70;

export type CompanionPosture = "joyful" | "calm" | "bored" | "tired";

export function companionPosture(companion: Pick<Companion, "mood" | "health">): CompanionPosture {
	if (companion.health < LOW_HEALTH) {
		return "tired";
	}

	if (companion.mood >= HIGH_MOOD) {
		return "joyful";
	}

	if (companion.mood >= LOW_MOOD) {
		return "calm";
	}

	return "bored";
}

export function companionStateLabel(companion: Pick<Companion, "mood" | "health">): string {
	switch (companionPosture(companion)) {
		case "tired":
			return "Fatigué";
		case "joyful":
			return "En pleine forme";
		case "calm":
			return "Serein";
		case "bored":
			return "En manque de sortie";
	}
}

export function companionBubble(companion: Pick<Companion, "mood" | "health">): string {
	switch (companionPosture(companion)) {
		case "tired":
			return "Je me sens tout mou… On marche un peu chaque jour ?";
		case "joyful":
			return "Hey, on sort ? 🌊";
		case "calm":
			return "On fait un tour ? J'ai besoin de bouger.";
		case "bored":
			return "Je m'ennuie… On sort marcher ?";
	}
}
