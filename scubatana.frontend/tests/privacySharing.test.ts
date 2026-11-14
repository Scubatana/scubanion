import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { friendGoalFill, sortFriendsBySteps, type Friendship } from "~/utils/friends";

const { httpRequest, navigateTo, storage } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>()
}));

vi.mock("~/utils/http", () => ({ httpRequest }));

vi.mock("@capacitor/preferences", () => ({
	Preferences: {
		get: async ({ key }: { key: string }) => ({ value: storage.get(key) ?? null }),
		set: async ({ key, value }: { key: string }) => {
			storage.set(key, value);
		},
		remove: async ({ key }: { key: string }) => {
			storage.delete(key);
		}
	}
}));

mockNuxtImport("navigateTo", () => navigateTo);

function makeFriend(id: number, pseudo: string, today: Friendship["player"]["today"], streak: Friendship["player"]["streak"]): Friendship {
	return {
		id,
		since: "2026-10-13",
		cheer: { sent: false, received: false },
		player: {
			id,
			pseudo,
			companion: null,
			today,
			streak
		}
	};
}

describe("friend privacy helpers and savePrivacy", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true);
	});

	it("handles masked friend daily steps and sorts shared friends before hidden ones", () => {
		expect(friendGoalFill(null)).toBe("0%");

		const friends = [
			makeFriend(1, "Zoe", null, null),
			makeFriend(2, "Yanis", { date: "2026-10-13", steps: 4200, goal: 5000 }, { days: 2, active: false }),
			makeFriend(3, "Amine", null, null),
			makeFriend(4, "Lina", { date: "2026-10-13", steps: 7100, goal: 5000 }, null)
		];

		expect(sortFriendsBySteps(friends).map((entry) => entry.player.pseudo)).toEqual(["Lina", "Yanis", "Amine", "Zoe"]);
	});

	it("persists friend privacy toggles via PUT /me/privacy", async () => {
		const updatedPlayer = {
			id: 7,
			email: "lea@example.com",
			pseudo: "Lea",
			birth_date: "2004-05-12",
			gender: null,
			ambition: "fitness" as const,
			profile_completed: true,
			privacy: { share_steps: true, share_streak: false },
			stats: { total_steps: 1000, quests_done: 0, friends_count: 1 }
		};

		httpRequest.mockResolvedValueOnce({ status: 200, data: { player: updatedPlayer } });

		await usePlayer().savePrivacy({ share_steps: true, share_streak: false });

		expect(httpRequest.mock.calls[0][0]).toBe("PUT");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/privacy$/);
		expect(httpRequest.mock.calls[0][3]).toEqual({ share_steps: true, share_streak: false });
		expect(usePlayer().player.value?.privacy).toEqual({ share_steps: true, share_streak: false });
	});
});
