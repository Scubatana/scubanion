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

const player = {
	id: 1,
	email: "lea@example.com",
	pseudo: "MarcheurDuSoir",
	birth_date: "2004-05-12",
	gender: "female",
	ambition: "fitness",
	profile_completed: true,
	stats: { total_steps: 4820, quests_done: 0, friends_count: 0 }
};

const companion = { name: "Scubanion", species: "scuba", color: "ocean", accessories: [] as string[], level: 1, xp: 0, xp_for_next_level: 1000, mood: 100, health: 100 };

const today = { date: "2026-10-12", steps: 147, goal: 5000 };

describe("usePlayer", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", false);
	});

	it("loads the player, the companion and the steps of the day", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { player, companion, today } });
		const state = usePlayer();

		await state.load();

		expect(httpRequest.mock.calls[0][0]).toBe("GET");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me$/);
		expect(state.player.value?.pseudo).toBe("MarcheurDuSoir");
		expect(state.companion.value?.level).toBe(1);
		expect(state.today.value?.steps).toBe(147);
	});

	it("syncs the profile flag of the session when loading", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { player, companion, today } });

		await usePlayer().load();

		expect(useSession().profileCompleted.value).toBe(true);
		expect(storage.get("session.profileCompleted")).toBe("true");
	});

	it("keeps what is shown when the server is unreachable", async () => {
		httpRequest.mockResolvedValueOnce({ status: 200, data: { player, companion, today } });
		const state = usePlayer();
		await state.load();
		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));

		await expect(state.load()).rejects.toMatchObject({ status: 0 });

		expect(state.companion.value?.name).toBe("Scubanion");
		expect(useSession().token.value).toBe("jeton-1");
	});

	it("saves the profile, stores the companion and marks the profile as completed", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { player, companion } });
		const state = usePlayer();
		const payload = { pseudo: "MarcheurDuSoir", birth_date: "2004-05-12", gender: null, ambition: "fitness" as const };

		await state.saveProfile(payload);

		expect(httpRequest.mock.calls[0][0]).toBe("PUT");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/profile$/);
		expect(httpRequest.mock.calls[0][3]).toEqual(payload);
		expect(state.companion.value?.name).toBe("Scubanion");
		expect(useSession().profileCompleted.value).toBe(true);
	});

	it("leaves the profile incomplete when the server refuses it", async () => {
		httpRequest.mockResolvedValue({ status: 422, data: { errors: { pseudo: ["Ce pseudo est déjà pris."] } } });

		const error = await usePlayer().saveProfile({ pseudo: "Pris", birth_date: "2004-05-12", gender: null, ambition: "fitness" }).catch((caught) => caught);

		expect(error.fields.pseudo).toBe("Ce pseudo est déjà pris.");
		expect(useSession().profileCompleted.value).toBe(false);
	});

	it("forgets everything on reset", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { player, companion, today } });
		const state = usePlayer();
		await state.load();

		state.reset();

		expect(state.player.value).toBeNull();
		expect(state.companion.value).toBeNull();
		expect(state.today.value).toBeNull();
	});

	it("saves the look of the companion and keeps the answer of the server", async () => {
		const look = { name: "Bulle", species: "dog", color: "coral", accessories: ["tophat"] };
		httpRequest.mockResolvedValue({ status: 200, data: { companion: { ...companion, ...look } } });
		const state = usePlayer();

		await state.saveCompanion(look);

		expect(httpRequest.mock.calls[0][0]).toBe("PUT");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/companion$/);
		expect(httpRequest.mock.calls[0][3]).toEqual(look);
		expect(state.companion.value?.name).toBe("Bulle");
		expect(state.companion.value?.accessories).toEqual(["tophat"]);
	});

	it("keeps the previous look when the server refuses the new one", async () => {
		httpRequest.mockResolvedValueOnce({ status: 200, data: { player, companion, today } });
		const state = usePlayer();
		await state.load();
		httpRequest.mockResolvedValueOnce({ status: 422, data: { errors: { accessories: ["Un seul accessoire par catégorie."] } } });

		const error = await state.saveCompanion({ name: "Bulle", species: "scuba", color: "ocean", accessories: ["tophat", "crown"] }).catch((caught) => caught);

		expect(error.fields.accessories).toBe("Un seul accessoire par catégorie.");
		expect(state.companion.value?.accessories).toEqual([]);
	});
});
