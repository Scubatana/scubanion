import type { CatalogAccessory, CatalogColor, CatalogSpecies, CompanionCatalog } from "./types";

export interface PlacedAccessory {
	id: string;
	image: string;
	style: Record<string, string>;
}

export interface ResolvedLook {
	species: CatalogSpecies;
	palette: Record<string, string>;
	accessories: PlacedAccessory[];
}

export function paletteStyle(color: CatalogColor | undefined): Record<string, string> {
	if (color === undefined) {
		return {};
	}

	return {
		"--companion-main": color.main,
		"--companion-shade": color.shade,
		"--companion-light": color.light
	};
}

export function toggleAccessory(selected: string[], accessoryId: string, accessories: CatalogAccessory[]): string[] {
	if (selected.includes(accessoryId)) {
		return selected.filter((id) => id !== accessoryId);
	}

	const category = accessories.find((accessory) => accessory.id === accessoryId)?.category;

	if (category === undefined) {
		return selected;
	}

	const sameCategory = accessories.filter((accessory) => accessory.category === category).map((accessory) => accessory.id);

	return [...selected.filter((id) => !sameCategory.includes(id)), accessoryId];
}

export function resolveLook(catalog: CompanionCatalog, speciesId: string, colorId: string, accessoryIds: string[]): ResolvedLook | null {
	const species = catalog.species.find((entry) => entry.id === speciesId);

	if (species === undefined) {
		return null;
	}

	const accessories: PlacedAccessory[] = [];

	for (const category of catalog.categories) {
		const anchor = species.anchors[category.id];
		const accessory = catalog.accessories.find((entry) => entry.category === category.id && accessoryIds.includes(entry.id));

		if (anchor === undefined || accessory === undefined) {
			continue;
		}

		accessories.push({
			id: accessory.id,
			image: accessory.image,
			style: {
				left: `${anchor.x}%`,
				top: `${anchor.y}%`,
				width: `${anchor.width}%`,
				transform: `translate(-${category.origin.x}%, -${category.origin.y}%)`
			}
		});
	}

	return {
		species,
		palette: paletteStyle(catalog.colors.find((entry) => entry.id === colorId)),
		accessories
	};
}
