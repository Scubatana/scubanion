import { describe, expect, it } from "vitest";
import { companionBubble } from "~/utils/companionMood";

describe("companionBubble", () => {
	it("invites to go out when the companion is in a good mood", () => {
		expect(companionBubble({ mood: 100, health: 100 })).toBe("Hey, on sort ? 🌊");
		expect(companionBubble({ mood: 70, health: 40 })).toBe("Hey, on sort ? 🌊");
	});

	it("asks for a walk when the mood drops", () => {
		expect(companionBubble({ mood: 50, health: 90 })).toBe("On fait un tour ? J'ai besoin de bouger.");
		expect(companionBubble({ mood: 10, health: 90 })).toBe("Je m'ennuie… On sort marcher ?");
	});

	it("speaks of its health first when the last days were too quiet", () => {
		expect(companionBubble({ mood: 100, health: 39 })).toBe("Je me sens tout mou… On marche un peu chaque jour ?");
	});
});
