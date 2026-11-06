import { describe, expect, it } from "vitest";
import {
	buildBbox,
	DEFAULT_MAP_CENTER,
	distanceMeters,
	filterMapEntries,
	formatDistance,
	MAX_BBOX_SPAN,
	projectOutline,
	projectPoint,
	serializeBbox,
	type MapPlace,
	type MapTerminal,
	type PlaceTypeEntry
} from "~/utils/map";

const placeTypes: PlaceTypeEntry[] = [
	{ code: "park", label: "Parc", icon: "tree", color: "#4FA871", on_map: true },
	{ code: "point_of_interest", label: "Point d'intérêt", icon: "pin", color: "#2D8FA5", on_map: true }
];

const places: MapPlace[] = [
	{ id: 1, name: "Colline du Château", type: "point_of_interest", latitude: 43.6953, longitude: 7.2806, radius_m: 150, outline: null },
	{ id: 2, name: "Jardin Albert Ier", type: "park", latitude: 43.6956, longitude: 7.2686, radius_m: 120, outline: null }
];

const terminals: MapTerminal[] = [
	{
		code: "chateau-01",
		name: "Borne du Château",
		online: true,
		last_seen_at: "2026-10-13T14:59:30+02:00",
		place: { id: 1, name: "Colline du Château", latitude: 43.6953, longitude: 7.2817 }
	},
	{
		code: "atelier-01",
		name: "Borne sans lieu",
		online: false,
		last_seen_at: null,
		place: null
	}
];

describe("map utils", () => {
	it("builds and serializes a bounding box strictly below the 0.5 degree server limit", () => {
		const box = buildBbox(DEFAULT_MAP_CENTER, 2.5);

		expect(box.east - box.west).toBeCloseTo(MAX_BBOX_SPAN, 5);
		expect(box.north - box.south).toBeCloseTo(MAX_BBOX_SPAN, 5);
		expect(serializeBbox(buildBbox({ latitude: 43.7, longitude: 7.25 }, 0.1))).toBe("7.2,43.65,7.3,43.75");
	});

	it("computes Haversine distances and formats meters and kilometers in French", () => {
		const distance = distanceMeters({ latitude: 43.6956, longitude: 7.2686 }, { latitude: 43.6953, longitude: 7.2806 });

		expect(distance).toBeGreaterThan(900);
		expect(distance).toBeLessThan(1050);
		expect(formatDistance(85)).toBe("85 m");
		expect(formatDistance(1420)).toBe("1,4 km");
	});

	it("projects coordinates and polygon outlines into the SVG viewport", () => {
		const box = buildBbox({ latitude: 43.7, longitude: 7.25 }, 0.1);
		const centerPoint = projectPoint({ latitude: 43.7, longitude: 7.25 }, box, 320, 240);
		const outsidePoint = projectPoint({ latitude: 44.5, longitude: 7.25 }, box, 320, 240);

		expect(centerPoint).toEqual({ x: 160, y: 120, inside: true });
		expect(outsidePoint.inside).toBe(false);
		expect(projectOutline([[43.7, 7.25], [43.75, 7.3]], box, 320, 240)).toBe("160,120 320,0");
	});

	it("filters places and terminals by category and accent-insensitive query, sorted by proximity", () => {
		const nearAlbert = { latitude: 43.6956, longitude: 7.2687 };

		const all = filterMapEntries(places, terminals, placeTypes, nearAlbert, "", "all");
		expect(all.map((entry) => entry.name)).toEqual(["Jardin Albert Ier", "Colline du Château", "Borne du Château"]);

		const onlyTerminals = filterMapEntries(places, terminals, placeTypes, nearAlbert, "", "terminals");
		expect(onlyTerminals).toHaveLength(1);
		expect(onlyTerminals[0].key).toBe("terminal-chateau-01");

		const accentQuery = filterMapEntries(places, terminals, placeTypes, nearAlbert, "chateau", "all");
		expect(accentQuery.map((entry) => entry.key)).toEqual(["place-1", "terminal-chateau-01"]);

		const onlyParks = filterMapEntries(places, terminals, placeTypes, nearAlbert, "", "park");
		expect(onlyParks.map((entry) => entry.name)).toEqual(["Jardin Albert Ier"]);
	});
});
