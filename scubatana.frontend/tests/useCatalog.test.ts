import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { httpRequest, navigateTo, storage } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>()
}));

vi.mock("~/utils/http", () => ({ httpRequest }));

vi.mock("@capacitor/preferences", () => ({
	Preferences: {
		get: async ({ key }: { key: string }) => ({ value: storage.get(key) ?? null }),
		set: async ({ key, value }: { key: string; value: string }) => {
			storage.set(key, value);
		},
		remove: async ({ key }: { key: string }) => {
			storage.delete(key);
		}
	}
}));

mockNuxtImport("navigateTo", () => navigateTo);

const catalog = { categories: [], species: [], colors: [], accessories: [] };

describe("useCatalog", () => {
	beforeEach(() => {
		httpRequest.mockReset();
		storage.clear();
		clearNuxtState();
	});

	it("fetches the catalog from the server", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: catalog });
		const state = useCatalog();

		await state.ensureLoaded();

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/companion\/catalog$/);
		expect(state.catalog.value).toEqual(catalog);
	});

	it("fetches the catalog only once", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: catalog });
		const state = useCatalog();

		await state.ensureLoaded();
		await state.ensureLoaded();

		expect(httpRequest).toHaveBeenCalledTimes(1);
	});

	it("stays empty and can retry when the server is unreachable", async () => {
		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));
		const state = useCatalog();

		await expect(state.ensureLoaded()).rejects.toMatchObject({ status: 0 });
		expect(state.catalog.value).toBeNull();

		httpRequest.mockResolvedValueOnce({ status: 200, data: catalog });
		await state.ensureLoaded();
		expect(state.catalog.value).toEqual(catalog);
	});

	it("fetches again after a reset", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: catalog });
		const state = useCatalog();
		await state.ensureLoaded();

		state.reset();
		await state.ensureLoaded();

		expect(httpRequest).toHaveBeenCalledTimes(2);
	});
});
