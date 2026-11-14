import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { enableAutoUnmount, flushPromises } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import BottomNav from "~/components/BottomNav.vue";

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

// Démonter avant que le test suivant vide l'état partagé.
enableAutoUnmount(afterEach);

function friendsLink(wrapper: Awaited<ReturnType<typeof mountSuspended>>) {
	return wrapper.get("a[href=\"/amis\"]");
}

describe("BottomNav", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true, 7);
		// Sans joueur connecté, useFriends remet son état à zéro.
		usePlayer().player.value = { id: 7, email: "lea@example.com", pseudo: "Lea", birth_date: "2004-05-12", gender: null, ambition: "fitness", profile_completed: true, stats: { total_steps: 0, quests_done: 0, friends_count: 0 } };
	});

	it("shows a dot on Amis when invitations are waiting, and says how many", async () => {
		httpRequest.mockResolvedValue({
			status: 200,
			data: { code: "K7Q2MZ4P", friends: [], received: [{ id: 13, sent_at: "2026-11-14T10:00:00+01:00", player: { id: 9, pseudo: "Sofia" } }], sent: [] }
		});
		const wrapper = await mountSuspended(BottomNav);
		await flushPromises();

		expect(friendsLink(wrapper).find(".dot").exists()).toBe(true);
		expect(friendsLink(wrapper).attributes("aria-label")).toBe("Amis, 1 invitation reçue");
	});

	it("shows no dot without invitations, and does not reload a list already loaded", async () => {
		const friends = useFriends();
		friends.loaded.value = true;
		const wrapper = await mountSuspended(BottomNav);
		await flushPromises();

		expect(friendsLink(wrapper).find(".dot").exists()).toBe(false);
		expect(friendsLink(wrapper).attributes("aria-label")).toBeUndefined();
		expect(httpRequest).not.toHaveBeenCalled();
	});
});
