import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DEFAULT_MAP_CENTER } from "~/utils/map";

const mocks = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	currentPosition: vi.fn(),
	storage: new Map<string, string>()
}));

vi.mock("~/utils/http", () => ({ httpRequest: mocks.httpRequest }));

vi.mock("~/utils/geolocation", () => ({ currentPosition: mocks.currentPosition }));

vi.mock("@capacitor/preferences", () => ({
	Preferences: {
		get: async ({ key }: { key: string }) => ({ value: mocks.storage.get(key) ?? null }),
		set: async ({ key, value }: { key: string; value: string }) => {
			mocks.storage.set(key, value);
		},
		remove: async ({ key }: { key: string }) => {
			mocks.storage.delete(key);
		}
	}
}));

mockNuxtImport("navigateTo", () => mocks.navigateTo);

describe("useMap", () => {
	beforeEach(async () => {
		mocks.httpRequest.mockReset();
		mocks.navigateTo.mockReset();
		mocks.currentPosition.mockReset();
		mocks.storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true);
	});

	it("loads visible place types, terminals and map places around the default center when GPS is unavailable", async () => {
		mocks.currentPosition.mockRejectedValue(new Error("Position indisponible"));
		mocks.httpRequest
			.mockResolvedValueOnce({
				status: 200,
				data: {
					place_types: [
						{ code: "park", label: "Parc", icon: "tree", color: "#4FA871", on_map: true },
						{ code: "hidden", label: "Caché", icon: "eye", color: "#000000", on_map: false }
					]
				}
			})
			.mockResolvedValueOnce({
				status: 200,
				data: {
					terminals: [{ code: "chateau-01", name: "Borne du Château", online: true, last_seen_at: null, place: { id: 1, name: "Château", latitude: 43.6953, longitude: 7.2817 } }]
				}
			})
			.mockResolvedValueOnce({
				status: 200,
				data: {
					truncated: false,
					places: [{ id: 1, name: "Colline du Château", type: "park", latitude: 43.6953, longitude: 7.2806, radius_m: 150, outline: null }]
				}
			});

		const map = useMap();
		await map.load();

		expect(map.center.value).toEqual(DEFAULT_MAP_CENTER);
		expect(map.playerLocation.value).toBeNull();
		expect(map.placeTypes.value.map((entry) => entry.code)).toEqual(["park"]);
		expect(map.terminals.value).toHaveLength(1);
		expect(map.places.value).toHaveLength(1);
		expect(mocks.httpRequest.mock.calls[2][1]).toMatch(/\/api\/map\?bbox=/);
	});

	it("centers on the player when GPS is granted and reloads the area when zooming", async () => {
		mocks.currentPosition.mockResolvedValue({
			latitude: 43.7001,
			longitude: 7.2654,
			accuracy_m: 10,
			mocked: false,
			recorded_at: "2026-10-13T15:00:00.000Z"
		});
		mocks.httpRequest
			.mockResolvedValueOnce({ status: 200, data: { place_types: [] } })
			.mockResolvedValueOnce({ status: 200, data: { terminals: [] } })
			.mockResolvedValueOnce({ status: 200, data: { truncated: false, places: [] } })
			.mockResolvedValueOnce({ status: 200, data: { truncated: true, places: [] } });

		const map = useMap();
		await map.load();

		expect(map.playerLocation.value).toEqual({ latitude: 43.7001, longitude: 7.2654 });
		expect(map.center.value).toEqual({ latitude: 43.7001, longitude: 7.2654 });

		await map.zoomOut();

		expect(map.zoomIndex.value).toBe(2);
		expect(map.truncated.value).toBe(true);
		expect(mocks.httpRequest).toHaveBeenCalledTimes(4);
	});
});
