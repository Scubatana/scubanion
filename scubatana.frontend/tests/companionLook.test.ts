import { describe, expect, it } from "vitest";
import { lockedAccessories, paletteStyle, resolveLook, toggleAccessory } from "~/utils/companionLook";
import type { CompanionCatalog } from "~/utils/types";

const catalog: CompanionCatalog = {
	categories: [
		{ id: "hat", label: "Chapeau", origin: { x: 50, y: 75 } },
		{ id: "glasses", label: "Yeux", origin: { x: 50, y: 50 } }
	],
	species: [
		{
			id: "scuba",
			label: "Scuba",
			svg: "<svg></svg>",
			anchors: { hat: { x: 50, y: 18, width: 56 }, glasses: { x: 50, y: 49.5, width: 44 } }
		},
		{
			id: "ghost",
			label: "Sans yeux",
			svg: "<svg></svg>",
			anchors: { hat: { x: 10, y: 20, width: 30 } }
		}
	],
	colors: [
		{ id: "ocean", label: "Océan", main: "#7FB7C4", shade: "#4E8A99", light: "#EAF6F8" },
		{ id: "coral", label: "Corail", main: "#F3A183", shade: "#D96A41", light: "#FDEDE6" }
	],
	accessories: [
		{ id: "tophat", label: "Haut-de-forme", category: "hat", image: "data:image/svg+xml;base64,tophat" },
		{ id: "crown", label: "Couronne", category: "hat", image: "data:image/svg+xml;base64,crown" },
		{ id: "sunglasses", label: "Lunettes de soleil", category: "glasses", image: "data:image/svg+xml;base64,sunglasses" }
	]
};

describe("toggleAccessory", () => {
	it("puts an accessory on", () => {
		expect(toggleAccessory([], "tophat", catalog.accessories)).toEqual(["tophat"]);
	});

	it("takes off an accessory already worn", () => {
		expect(toggleAccessory(["tophat", "sunglasses"], "tophat", catalog.accessories)).toEqual(["sunglasses"]);
	});

	it("replaces the accessory of the same category and keeps the others", () => {
		expect(toggleAccessory(["tophat", "sunglasses"], "crown", catalog.accessories)).toEqual(["sunglasses", "crown"]);
	});

	it("ignores an accessory missing from the catalog", () => {
		expect(toggleAccessory(["tophat"], "jetpack", catalog.accessories)).toEqual(["tophat"]);
	});

	it("never changes the selection it receives", () => {
		const selected = ["tophat"];

		toggleAccessory(selected, "crown", catalog.accessories);

		expect(selected).toEqual(["tophat"]);
	});
});

describe("paletteStyle", () => {
	it("exposes the three tones of a color as style variables", () => {
		expect(paletteStyle(catalog.colors[1])).toEqual({ "--companion-main": "#F3A183", "--companion-shade": "#D96A41", "--companion-light": "#FDEDE6" });
	});

	it("sets nothing for an unknown color so the base keeps its own tones", () => {
		expect(paletteStyle(undefined)).toEqual({});
	});
});

describe("resolveLook", () => {
	it("pins every accessory on the anchor of its category, shifted by the origin of that category", () => {
		const look = resolveLook(catalog, "scuba", "coral", ["tophat", "sunglasses"]);

		expect(look?.species.id).toBe("scuba");
		expect(look?.palette["--companion-main"]).toBe("#F3A183");
		expect(look?.accessories).toEqual([
			{ id: "tophat", image: "data:image/svg+xml;base64,tophat", style: { left: "50%", top: "18%", width: "56%", transform: "translate(-50%, -75%)" } },
			{ id: "sunglasses", image: "data:image/svg+xml;base64,sunglasses", style: { left: "50%", top: "49.5%", width: "44%", transform: "translate(-50%, -50%)" } }
		]);
	});

	it("keeps the look complete when the color changes while accessories are worn", () => {
		const before = resolveLook(catalog, "scuba", "ocean", ["tophat", "sunglasses"]);
		const after = resolveLook(catalog, "scuba", "coral", ["tophat", "sunglasses"]);

		expect(after?.accessories).toEqual(before?.accessories);
		expect(after?.species.svg).toBe(before?.species.svg);
		expect(after?.palette).not.toEqual(before?.palette);
	});

	it("draws accessories in the order of the categories, whatever the order they were chosen in", () => {
		const look = resolveLook(catalog, "scuba", "ocean", ["sunglasses", "tophat"]);

		expect(look?.accessories.map((accessory) => accessory.id)).toEqual(["tophat", "sunglasses"]);
	});

	it("places the same accessory differently on another base without any accessory specific setting", () => {
		const look = resolveLook(catalog, "ghost", "ocean", ["tophat"]);

		expect(look?.accessories[0].style).toEqual({ left: "10%", top: "20%", width: "30%", transform: "translate(-50%, -75%)" });
	});

	it("skips accessories that are unknown or have no anchor on the base", () => {
		const look = resolveLook(catalog, "ghost", "ocean", ["jetpack", "sunglasses", "crown"]);

		expect(look?.accessories.map((accessory) => accessory.id)).toEqual(["crown"]);
	});

	it("falls back to the tones of the base for an unknown color", () => {
		expect(resolveLook(catalog, "scuba", "invisible", [])?.palette).toEqual({});
	});

	it("returns nothing for a base missing from the catalog", () => {
		expect(resolveLook(catalog, "dragon", "ocean", [])).toBeNull();
	});
});

describe("lockedAccessories", () => {
	const shop = [
		{ id: "tophat", label: "Haut-de-forme", category: "hat", image: "a" },
		{ id: "crown", label: "Couronne", category: "hat", image: "b", earnable: true },
		{ id: "medal", label: "Médaille", category: "neck", image: "c", earnable: true }
	];

	it("locks the accessories to win that the player has not unlocked", () => {
		expect(lockedAccessories(shop, [], [])).toEqual(["crown", "medal"]);
		expect(lockedAccessories(shop, ["crown"], [])).toEqual(["medal"]);
	});

	it("never locks an accessory the companion already wears", () => {
		expect(lockedAccessories(shop, [], ["medal"])).toEqual(["crown"]);
	});
});
