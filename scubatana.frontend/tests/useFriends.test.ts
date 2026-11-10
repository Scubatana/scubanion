import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { formatFriendCode, friendGoalFill, normalizeFriendCodeInput, qrRefreshDelay, sortFriendsBySteps, type FriendsView } from "~/utils/friends";

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

const sampleView: FriendsView = {
	code: "K7Q2MZ4P",
	friends: [
		{
			id: 12,
			since: "2026-10-13",
			player: {
				id: 3,
				pseudo: "Yanis",
				companion: { name: "Bulle", species: "dog", color: "sand", accessories: ["cap"], level: 3 },
				today: { date: "2026-10-13", steps: 4200, goal: 5000 },
				streak: { days: 2, active: false }
			}
		}
	],
	received: [{ id: 13, sent_at: "2026-10-13T15:00:00+02:00", player: { id: 9, pseudo: "Lina" } }],
	sent: [{ id: 14, sent_at: "2026-10-13T14:30:00+02:00", player: { id: 5, pseudo: "Tom" } }]
};

describe("friends utils", () => {
	it("normalizes and formats 8-character friend codes cleanly", () => {
		expect(normalizeFriendCodeInput(" k7q2 - mz4p ")).toBe("K7Q2MZ4P");
		expect(formatFriendCode("k7q2mz4p")).toBe("K7Q2-MZ4P");
		expect(formatFriendCode("COURT")).toBe("COURT");
	});

	it("computes bounded goal fills and sorts friends by daily steps descending", () => {
		expect(friendGoalFill({ date: "2026-10-13", steps: 2500, goal: 5000 })).toBe("50%");
		expect(friendGoalFill({ date: "2026-10-13", steps: 8000, goal: 5000 })).toBe("100%");
		expect(friendGoalFill({ date: "2026-10-13", steps: 100, goal: 0 })).toBe("0%");

		const sorted = sortFriendsBySteps([
			sampleView.friends[0],
			{
				id: 15,
				since: "2026-10-13",
				player: {
					id: 4,
					pseudo: "Amine",
					companion: null,
					today: { date: "2026-10-13", steps: 7100, goal: 6000 },
					streak: { days: 5, active: true }
				}
			}
		]);

		expect(sorted.map((entry) => entry.player.pseudo)).toEqual(["Amine", "Yanis"]);
	});

	it("refreshes the QR ten seconds before it expires, but never faster than every five seconds", () => {
		const now = Date.parse("2026-11-09T16:00:00+01:00");

		expect(qrRefreshDelay("2026-11-09T16:01:00+01:00", now)).toBe(50_000);
		expect(qrRefreshDelay("2026-11-09T16:00:12+01:00", now)).toBe(5_000);
		expect(qrRefreshDelay("2026-11-09T15:59:00+01:00", now)).toBe(5_000);
		expect(qrRefreshDelay("pas une date", now)).toBe(5_000);
	});
});

describe("useFriends", () => {
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

	it("loads the friends view and updates the player's friends_count", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: sampleView });
		const friends = useFriends();

		await friends.load();

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/friends$/);
		expect(friends.code.value).toBe("K7Q2MZ4P");
		expect(friends.friends.value).toHaveLength(1);
		expect(friends.received.value).toHaveLength(1);
		expect(friends.sent.value).toHaveLength(1);
		expect(usePlayer().player.value?.stats.friends_count).toBe(1);
	});

	it("sends a normalized invite code and distinguishes pending vs mutual invitations", async () => {
		const friends = useFriends();
		httpRequest.mockResolvedValueOnce({ status: 200, data: sampleView });
		await friends.load();

		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: {
				...sampleView,
				sent: [...sampleView.sent, { id: 18, sent_at: "2026-10-13T16:00:00+02:00", player: { id: 11, pseudo: "Chloe" } }]
			}
		});

		const pendingOutcome = await friends.invite(" abcd - efgh ");
		expect(httpRequest.mock.calls[1][3]).toEqual({ code: "ABCDEFGH" });
		expect(pendingOutcome).toBe("sent");

		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: {
				...sampleView,
				received: [],
				friends: [
					...sampleView.friends,
					{
						id: 13,
						since: "2026-10-13",
						player: {
							id: 9,
							pseudo: "Lina",
							companion: null,
							today: { date: "2026-10-13", steps: 1000, goal: 5000 },
							streak: { days: 1, active: false }
						}
					}
				]
			}
		});

		const mutualOutcome = await friends.invite("LINA2345");
		expect(mutualOutcome).toBe("mutual");
		expect(usePlayer().player.value?.stats.friends_count).toBe(2);
	});

	it("accepts and deletes invitations or friendships using the returned view", async () => {
		const friends = useFriends();
		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: { ...sampleView, received: [], friends: [...sampleView.friends] }
		});

		await friends.accept(13);
		expect(httpRequest.mock.calls[0][0]).toBe("POST");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/friends\/13\/accept$/);
		expect(friends.received.value).toEqual([]);

		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: { ...sampleView, friends: [], received: [], sent: [] }
		});

		await friends.remove(12);
		expect(httpRequest.mock.calls[1][0]).toBe("DELETE");
		expect(httpRequest.mock.calls[1][1]).toMatch(/\/api\/me\/friends\/12$/);
		expect(friends.friends.value).toEqual([]);
		expect(usePlayer().player.value?.stats.friends_count).toBe(0);
	});

	it("asks the server for a QR token to display", async () => {
		const token = { token: "eyJpdiI6...", expires_at: "2026-11-09T16:01:00+01:00" };
		httpRequest.mockResolvedValueOnce({ status: 200, data: token });

		await expect(useFriends().qrToken()).resolves.toEqual(token);
		expect(httpRequest.mock.calls[0][0]).toBe("GET");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/friends\/qr$/);
	});

	it("adds the friend whose QR was scanned and returns their pseudo", async () => {
		const friends = useFriends();
		httpRequest.mockResolvedValueOnce({ status: 200, data: sampleView });
		await friends.load();

		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: {
				...sampleView,
				friends: [
					...sampleView.friends,
					{
						id: 20,
						since: "2026-11-09",
						player: {
							id: 9,
							pseudo: "Lina",
							companion: null,
							today: { date: "2026-11-09", steps: 0, goal: 5000 },
							streak: { days: 0, active: false }
						}
					}
				]
			}
		});

		await expect(friends.scanQr("  eyJpdiI6...  ")).resolves.toBe("Lina");
		expect(httpRequest.mock.calls[1][0]).toBe("POST");
		expect(httpRequest.mock.calls[1][1]).toMatch(/\/api\/me\/friends\/qr$/);
		expect(httpRequest.mock.calls[1][3]).toEqual({ token: "eyJpdiI6..." });
		expect(friends.friends.value).toHaveLength(2);
		expect(usePlayer().player.value?.stats.friends_count).toBe(2);
	});

	it("resets friends state on logout and on 401 unauthenticated response", async () => {
		const friends = useFriends();
		httpRequest.mockResolvedValueOnce({ status: 200, data: sampleView });
		await friends.load();
		expect(friends.friends.value).toHaveLength(1);
		await useAuth().logout();
		expect(friends.friends.value).toEqual([]);
		expect(friends.code.value).toBe("");
		expect(friends.loaded.value).toBe(false);
		await useSession().open("jeton-2", true);
		usePlayer().player.value = {
			id: 2,
			email: "autre@example.com",
			pseudo: "Autre",
			birth_date: "2000-01-01",
			gender: null,
			ambition: "explore",
			profile_completed: true,
			stats: { total_steps: 0, quests_done: 0, friends_count: 0 }
		};
		httpRequest.mockResolvedValueOnce({ status: 200, data: sampleView });
		await friends.load();
		expect(friends.friends.value).toHaveLength(1);
		httpRequest.mockResolvedValueOnce({ status: 401, data: { message: "Unauthenticated." } });
		await expect(useApi().get("/me/friends")).rejects.toThrow();
		expect(friends.friends.value).toEqual([]);
		expect(friends.code.value).toBe("");
		expect(friends.loaded.value).toBe(false);
	});
});
