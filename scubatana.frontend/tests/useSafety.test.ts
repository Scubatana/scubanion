import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { BlocksView } from "~/utils/safety";

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

const sampleView: BlocksView = {
	blocked: [{ id: 7, since: "2026-11-09", player: { id: 3, pseudo: "Yanis" } }]
};

describe("useSafety", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true);
		usePlayer().player.value = {
			id: 1,
			email: "lea@example.com",
			pseudo: "Lea",
			birth_date: "2004-05-12",
			gender: null,
			ambition: "fitness",
			profile_completed: true,
			stats: { total_steps: 1200, quests_done: 1, friends_count: 0 }
		};
	});

	it("loads the list of blocked players", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: sampleView });
		const safety = useSafety();

		await safety.load();

		expect(httpRequest.mock.calls[0][0]).toBe("GET");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/blocks$/);
		expect(safety.blocked.value).toEqual(sampleView.blocked);
		expect(safety.loaded.value).toBe(true);
	});

	it("blocks a player by id and unblocks with the block id", async () => {
		const safety = useSafety();
		httpRequest.mockResolvedValueOnce({ status: 200, data: sampleView });

		await safety.block(3);
		expect(httpRequest.mock.calls[0][0]).toBe("POST");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/blocks$/);
		expect(httpRequest.mock.calls[0][3]).toEqual({ player_id: 3 });
		expect(safety.blocked.value).toHaveLength(1);

		httpRequest.mockResolvedValueOnce({ status: 200, data: { blocked: [] } });

		await safety.unblock(7);
		expect(httpRequest.mock.calls[1][0]).toBe("DELETE");
		expect(httpRequest.mock.calls[1][1]).toMatch(/\/api\/me\/blocks\/7$/);
		expect(safety.blocked.value).toEqual([]);
	});

	it("reports a player with a trimmed comment, or without one when blank", async () => {
		const safety = useSafety();
		const message = "Merci, ton signalement a été envoyé. L'équipe va l'examiner.";
		httpRequest.mockResolvedValue({ status: 201, data: { message } });

		await expect(safety.report(3, "behavior", "  Messages insistants.  ")).resolves.toBe(message);
		expect(httpRequest.mock.calls[0][0]).toBe("POST");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/reports$/);
		expect(httpRequest.mock.calls[0][3]).toEqual({ player_id: 3, reason: "behavior", comment: "Messages insistants." });

		await safety.report(3, "pseudo", "   ");
		expect(httpRequest.mock.calls[1][3]).toEqual({ player_id: 3, reason: "pseudo" });
	});

	it("resets the blocked list on logout", async () => {
		const safety = useSafety();
		httpRequest.mockResolvedValueOnce({ status: 200, data: sampleView });
		await safety.load();

		await useAuth().logout();

		expect(safety.blocked.value).toEqual([]);
		expect(safety.loaded.value).toBe(false);
	});
});
