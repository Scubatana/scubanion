import { Preferences } from "@capacitor/preferences";
import { currentPosition } from "~/utils/geolocation";
import {
	buildBbox,
	DEFAULT_MAP_CENTER,
	serializeBbox,
	ZOOM_SPANS,
	type BoundingBox,
	type Coordinates,
	type MapPlace,
	type MapTerminal,
	type PlaceTypeEntry
} from "~/utils/map";

interface MapResponse {
	truncated: boolean;
	places: MapPlace[];
}

interface CatalogTypesResponse {
	place_types: PlaceTypeEntry[];
}

interface TerminalsResponse {
	terminals: MapTerminal[];
}

async function isLocationConsentGranted(): Promise<boolean> {
	const playerId = usePlayer().player.value?.id;

	if (playerId === undefined) {
		return false;
	}

	try {
		const raw = (await Preferences.get({ key: `consents.${playerId}` })).value;

		if (raw === null) {
			return false;
		}

		const parsed = JSON.parse(raw) as { location?: boolean } | null;

		return parsed?.location === true;
	} catch {
		return false;
	}
}

export function useMap() {
	const center = useState<Coordinates>("map-center", () => ({ ...DEFAULT_MAP_CENTER }));
	const playerLocation = useState<Coordinates | null>("map-player-location", () => null);
	const zoomIndex = useState<number>("map-zoom-index", () => 1);
	const placeTypes = useState<PlaceTypeEntry[]>("map-place-types", () => []);
	const places = useState<MapPlace[]>("map-places", () => []);
	const terminals = useState<MapTerminal[]>("map-terminals", () => []);
	const truncated = useState<boolean>("map-truncated", () => false);
	const loaded = useState<boolean>("map-loaded", () => false);

	const session = useSession();
	const player = usePlayer();

	watch([session.token, player.player], ([token, currentPlayer]) => {
		if (token === null || currentPlayer === null) {
			reset();
		}
	}, { immediate: true });

	const span = computed(() => ZOOM_SPANS[zoomIndex.value] ?? ZOOM_SPANS[1]);
	const bbox = computed<BoundingBox>(() => buildBbox(center.value, span.value));
	const referencePoint = computed<Coordinates>(() => playerLocation.value ?? center.value);

	async function loadArea(): Promise<void> {
		const response = await useApi().get<MapResponse>(`/map?bbox=${serializeBbox(bbox.value)}`);

		places.value = response.places;
		truncated.value = response.truncated;
	}

	async function locatePlayer(askPermission: boolean): Promise<boolean> {
		if (!(await isLocationConsentGranted())) {
			playerLocation.value = null;

			return false;
		}

		try {
			const fix = await currentPosition(askPermission);
			const coords = { latitude: fix.latitude, longitude: fix.longitude };

			playerLocation.value = coords;
			center.value = coords;

			return true;
		} catch {
			return false;
		}
	}

	async function load(): Promise<void> {
		const api = useApi();

		await locatePlayer(false);

		const [typesResponse, terminalsResponse] = await Promise.all([
			placeTypes.value.length > 0 ? Promise.resolve(null) : api.get<CatalogTypesResponse>("/catalog/types"),
			api.get<TerminalsResponse>("/bornes")
		]);

		if (typesResponse !== null) {
			placeTypes.value = typesResponse.place_types.filter((entry) => entry.on_map);
		}

		terminals.value = terminalsResponse.terminals;
		await loadArea();
		loaded.value = true;
	}

	async function recenterOnPlayer(): Promise<boolean> {
		const found = await locatePlayer(true);

		if (found) {
			await loadArea();
		}

		return found;
	}

	async function zoomIn(): Promise<void> {
		if (zoomIndex.value <= 0) {
			return;
		}

		zoomIndex.value -= 1;
		await loadArea();
	}

	async function zoomOut(): Promise<void> {
		if (zoomIndex.value >= ZOOM_SPANS.length - 1) {
			return;
		}

		zoomIndex.value += 1;
		await loadArea();
	}

	async function pan(latStep: number, lngStep: number): Promise<void> {
		const delta = span.value * 0.35;

		center.value = {
			latitude: Math.max(-89.5, Math.min(89.5, center.value.latitude + latStep * delta)),
			longitude: Math.max(-179.5, Math.min(179.5, center.value.longitude + lngStep * delta))
		};
		await loadArea();
	}

	async function focusOn(coords: Coordinates): Promise<void> {
		center.value = { ...coords };
		await loadArea();
	}

	function reset(): void {
		center.value = { ...DEFAULT_MAP_CENTER };
		playerLocation.value = null;
		zoomIndex.value = 1;
		placeTypes.value = [];
		places.value = [];
		terminals.value = [];
		truncated.value = false;
		loaded.value = false;
	}

	return {
		center,
		playerLocation,
		zoomIndex,
		bbox,
		referencePoint,
		placeTypes,
		places,
		terminals,
		truncated,
		loaded,
		load,
		loadArea,
		recenterOnPlayer,
		zoomIn,
		zoomOut,
		pan,
		focusOn,
		reset
	};
}
