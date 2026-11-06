export interface Coordinates {
	latitude: number;
	longitude: number;
}

export interface BoundingBox {
	west: number;
	south: number;
	east: number;
	north: number;
}

export interface PlaceTypeEntry {
	code: string;
	label: string;
	icon: string;
	color: string;
	on_map: boolean;
}

export interface MapPlace {
	id: number;
	name: string;
	type: string;
	latitude: number;
	longitude: number;
	radius_m: number | null;
	outline: [number, number][] | null;
}

export interface MapTerminal {
	code: string;
	name: string;
	online: boolean;
	last_seen_at: string | null;
	place: {
		id: number;
		name: string;
		latitude: number;
		longitude: number;
	} | null;
}

export type MapFilter = "all" | "terminals" | string;

export interface NearbyMapEntry {
	key: string;
	kind: "place" | "terminal";
	name: string;
	subtitle: string;
	color: string;
	online: boolean | null;
	radiusMeters: number | null;
	coordinates: Coordinates;
	distanceMeters: number;
}

export const DEFAULT_MAP_CENTER: Coordinates = {
	latitude: 43.697,
	longitude: 7.274
};

export const ZOOM_SPANS = [0.02, 0.06, 0.16, 0.36] as const;

export const MAX_BBOX_SPAN = 0.48;

const EARTH_RADIUS_METERS = 6371000;

export function buildBbox(center: Coordinates, spanDegrees: number): BoundingBox {
	const safeSpan = Math.min(MAX_BBOX_SPAN, Math.max(0.005, spanDegrees));
	const half = safeSpan / 2;
	const south = Math.max(-90, center.latitude - half);
	const north = Math.min(90, center.latitude + half);
	const west = Math.max(-180, center.longitude - half);
	const east = Math.min(180, center.longitude + half);

	return { west, south, east, north };
}

export function serializeBbox(box: BoundingBox): string {
	return [box.west, box.south, box.east, box.north].map((value) => Number(value.toFixed(5))).join(",");
}

export function distanceMeters(from: Coordinates, to: Coordinates): number {
	const toRad = (deg: number) => (deg * Math.PI) / 180;
	const dLat = toRad(to.latitude - from.latitude);
	const dLng = toRad(to.longitude - from.longitude);
	const lat1 = toRad(from.latitude);
	const lat2 = toRad(to.latitude);
	const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;

	return Math.round(2 * EARTH_RADIUS_METERS * Math.asin(Math.min(1, Math.sqrt(a))));
}

export function formatDistance(meters: number): string {
	if (meters < 1000) {
		return `${Math.max(0, Math.round(meters))} m`;
	}

	const km = (meters / 1000).toFixed(1).replace(".", ",");

	return `${km} km`;
}

export function projectPoint(point: Coordinates, box: BoundingBox, width = 320, height = 240): { x: number; y: number; inside: boolean } {
	const lngSpan = Math.max(0.0001, box.east - box.west);
	const latSpan = Math.max(0.0001, box.north - box.south);
	const ratioX = (point.longitude - box.west) / lngSpan;
	const ratioY = 1 - (point.latitude - box.south) / latSpan;
	const inside = ratioX >= 0 && ratioX <= 1 && ratioY >= 0 && ratioY <= 1;

	return {
		x: Number((ratioX * width).toFixed(1)),
		y: Number((ratioY * height).toFixed(1)),
		inside
	};
}

export function projectOutline(outline: [number, number][], box: BoundingBox, width = 320, height = 240): string {
	return outline
		.map(([latitude, longitude]) => {
			const projected = projectPoint({ latitude, longitude }, box, width, height);

			return `${projected.x},${projected.y}`;
		})
		.join(" ");
}

export function normalizeSearch(value: string): string {
	return value
		.normalize("NFD")
		.replace(/[\u0300-\u036f]/g, "")
		.toLowerCase()
		.trim();
}

export function filterMapEntries(
	places: MapPlace[],
	terminals: MapTerminal[],
	placeTypes: PlaceTypeEntry[],
	reference: Coordinates,
	query: string,
	filter: MapFilter
): NearbyMapEntry[] {
	const needle = normalizeSearch(query);
	const typesByCode = new Map(placeTypes.map((entry) => [entry.code, entry]));
	const results: NearbyMapEntry[] = [];

	if (filter === "all" || filter === "terminals") {
		for (const terminal of terminals) {
			if (terminal.place === null) {
				continue;
			}

			const searchable = normalizeSearch(`${terminal.name} ${terminal.code} ${terminal.place.name} borne`);

			if (needle !== "" && !searchable.includes(needle)) {
				continue;
			}

			const coordinates = { latitude: terminal.place.latitude, longitude: terminal.place.longitude };

			results.push({
				key: `terminal-${terminal.code}`,
				kind: "terminal",
				name: terminal.name,
				subtitle: `Borne · ${terminal.place.name}`,
				color: terminal.online ? "#1E8A96" : "#8FA5AC",
				online: terminal.online,
				radiusMeters: null,
				coordinates,
				distanceMeters: distanceMeters(reference, coordinates)
			});
		}
	}

	if (filter !== "terminals") {
		for (const place of places) {
			if (filter !== "all" && place.type !== filter) {
				continue;
			}

			const placeType = typesByCode.get(place.type);
			const typeLabel = placeType?.label ?? place.type;
			const searchable = normalizeSearch(`${place.name} ${typeLabel}`);

			if (needle !== "" && !searchable.includes(needle)) {
				continue;
			}

			const coordinates = { latitude: place.latitude, longitude: place.longitude };

			results.push({
				key: `place-${place.id}`,
				kind: "place",
				name: place.name,
				subtitle: typeLabel,
				color: placeType?.color ?? "#2D8FA5",
				online: null,
				radiusMeters: place.radius_m,
				coordinates,
				distanceMeters: distanceMeters(reference, coordinates)
			});
		}
	}

	return results.sort((first, second) => first.distanceMeters - second.distanceMeters || first.name.localeCompare(second.name, "fr"));
}
