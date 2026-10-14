import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { httpRequest, navigateTo, storage, settled } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>(),
	settled: vi.fn()
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

mockNuxtImport("useSteps", () => () => ({ settled }));

const quest = { id: 4, quest_id: 1, name: "Dix petits pas", description: null, icon: null, period: "once", expires_at: null, status: "active", step: 1, steps: 1, tasks: [], step_rewards: [], quest_rewards: [{ xp: 300, accessory: "crown" }], rewards_to_claim: [] };

const other = { ...quest, id: 5, name: "Balade du jour" };

describe("useQuests", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		settled.mockReset();
		settled.mockResolvedValue(undefined);
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true);
	});

	it("loads the quests of the player", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { quests: [quest, other] } });
		const state = useQuests();

		await state.load();

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/quests$/);
		expect(state.quests.value.map((entry) => entry.name)).toEqual(["Dix petits pas", "Balade du jour"]);
	});

	it("asks for the quests only once the steps walked while the app was closed are sent", async () => {
		let stepsSent: () => void = () => undefined;
		settled.mockReturnValue(new Promise<void>((resolve) => {
			stepsSent = resolve;
		}));
		httpRequest.mockResolvedValue({ status: 200, data: { quests: [quest] } });

		const loading = useQuests().load();
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(httpRequest).not.toHaveBeenCalled();

		stepsSent();
		await loading;

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/quests$/);
	});

	it("replaces a quest with the answer of an action, or drops it when there is nothing left to show", async () => {
		const state = useQuests();
		state.quests.value = [quest, other];
		httpRequest.mockResolvedValueOnce({ status: 200, data: { status: "active", quest: { ...quest, step: 2 } } });

		expect(await state.act(4, 9, { code: "X" })).toBe("active");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/quests\/4\/tasks\/9$/);
		expect(httpRequest.mock.calls[0][3]).toMatchObject({ code: "X" });
		expect(httpRequest.mock.calls[0][3].recorded_at).toMatch(/^\d{4}-\d{2}-\d{2}T/);
		expect(state.quests.value[0].step).toBe(2);

		httpRequest.mockResolvedValueOnce({ status: 200, data: { status: "failed", quest: null } });

		expect(await state.act(4, 9, { question: 0, choice: 1 })).toBe("failed");
		expect(state.quests.value.map((entry) => entry.id)).toEqual([5]);
	});

	it("claims the rewards, hides the finished quest and keeps the companion sent back", async () => {
		const state = useQuests();
		state.quests.value = [{ ...quest, status: "completed", rewards_to_claim: [{ id: 1, xp: 300, accessory: "crown" }] }];
		const companion = { name: "Scubanion", species: "scuba", color: "ocean", accessories: [], unlocked_accessories: ["crown"], level: 1, xp: 300, xp_for_next_level: 1000, mood: 100, health: 100 };
		httpRequest.mockResolvedValue({ status: 200, data: { claimed: [{ id: 1, xp: 300, accessory: "crown" }], quest: null, companion } });

		const claimed = await state.claim(4);

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/quests\/4\/claim$/);
		expect(claimed).toEqual([{ id: 1, xp: 300, accessory: "crown" }]);
		expect(state.quests.value).toEqual([]);
		expect(usePlayer().companion.value?.unlocked_accessories).toEqual(["crown"]);
	});

	it("keeps the quests when the server refuses an action", async () => {
		const state = useQuests();
		state.quests.value = [quest];
		httpRequest.mockResolvedValue({ status: 422, data: { errors: { position: ["Tu n'es pas encore sur place."] } } });

		const error = await state.act(4, 9, { latitude: 1, longitude: 2 }).catch((caught) => caught);

		expect(error.fields.position).toBe("Tu n'es pas encore sur place.");
		expect(state.quests.value).toEqual([quest]);
	});

	it("keeps a location or code validation made without network and sends it once the network is back", async () => {
		usePlayer().player.value = { id: 7 } as never;
		const state = useQuests();
		state.quests.value = [quest];
		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));

		const outcome = await state.act(4, 9, { code: "X", recorded_at: "2026-10-13T20:00:00.000Z" }, true);

		expect(outcome).toBe("queued");
		expect(state.waiting(4, 9)).toBe(true);
		expect(JSON.parse(storage.get("quests.pending.7") ?? "[]")).toHaveLength(1);

		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));
		await state.flush();
		expect(state.waiting(4, 9)).toBe(true);

		httpRequest.mockResolvedValueOnce({ status: 200, data: { status: "active", quest: { ...quest, step: 2 } } });
		await state.flush();

		expect(httpRequest.mock.calls[2][3]).toEqual({ code: "X", recorded_at: "2026-10-13T20:00:00.000Z" });
		expect(state.waiting(4, 9)).toBe(false);
		expect(state.quests.value[0].step).toBe(2);
		expect(JSON.parse(storage.get("quests.pending.7") ?? "[]")).toEqual([]);
	});

	it("drops a waiting validation the server refuses, and never queues a quiz answer", async () => {
		usePlayer().player.value = { id: 7 } as never;
		const state = useQuests();
		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));
		await state.act(4, 9, { latitude: 1, longitude: 2 }, true);
		httpRequest.mockResolvedValueOnce({ status: 422, data: { errors: { position: ["Tu n'es pas encore sur place."] } } });

		await state.flush();

		expect(state.pending.value).toEqual([]);

		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));
		await expect(state.act(4, 9, { question: 0, choice: 1 })).rejects.toMatchObject({ status: 0 });
		expect(state.pending.value).toEqual([]);
	});

	it("finds the waiting validations of a player again after the app restarts", async () => {
		storage.set("quests.pending.7", JSON.stringify([{ questId: 4, taskId: 9, body: { code: "X" } }]));
		usePlayer().player.value = { id: 7 } as never;
		httpRequest.mockResolvedValueOnce({ status: 200, data: { status: "completed", quest: null } });
		httpRequest.mockResolvedValueOnce({ status: 200, data: { quests: [] } });

		await useQuests().load();

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/quests\/4\/tasks\/9$/);
		expect(httpRequest.mock.calls[1][1]).toMatch(/\/api\/me\/quests$/);
	});
});
