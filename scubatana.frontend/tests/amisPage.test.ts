import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { flushPromises } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AmisPage from "~/pages/amis.vue";
import { FRIEND_SEARCH_FROM, type Friendship, type FriendsView } from "~/utils/friends";

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

vi.mock("@capacitor/app", () => ({
	App: { addListener: async () => ({ remove: async () => undefined }) }
}));

mockNuxtImport("navigateTo", () => navigateTo);

const view: FriendsView = {
	code: "K7Q2MZ4P",
	friends: [
		{
			id: 12,
			since: "2026-10-13",
			cheer: { sent: false, received: false },
			player: {
				id: 3,
				pseudo: "Yanis",
				companion: { name: "Bulle", species: "dog", color: "sand", accessories: [], level: 3 },
				today: { date: "2026-11-14", steps: 4200, goal: 5000 },
				streak: { days: 2, active: false }
			}
		}
	],
	received: [],
	sent: []
};

function answer(friends: FriendsView): void {
	httpRequest.mockImplementation(async (_method: string, url: string) => {
		if (url.endsWith("/companion/catalog")) {
			return { status: 200, data: { categories: [], species: [], colors: [], accessories: [] } };
		}

		return { status: 200, data: friends };
	});
}

let mounted: Awaited<ReturnType<typeof mountSuspended>> | null = null;

async function mountPage() {
	mounted = await mountSuspended(AmisPage, { global: { stubs: { teleport: true } } });
	await flushPromises();

	return mounted;
}

describe("page Amis", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true, 7);
		answer(view);
	});

	// Démonter avant que le test suivant vide l'état partagé, sinon la page restée montée lit un état effacé.
	afterEach(() => {
		mounted?.unmount();
		mounted = null;
	});

	it("opens a friend's profile from the list, and reloads the list to show fresh steps", async () => {
		const wrapper = await mountPage();
		const loadsBefore = httpRequest.mock.calls.filter(([, url]) => String(url).endsWith("/me/friends")).length;

		await wrapper.get("button.profile-btn").trigger("click");
		await flushPromises();

		expect(wrapper.find("[aria-labelledby=\"friend-profile-title\"]").exists()).toBe(true);
		expect(wrapper.get("#friend-profile-title").text()).toBe("Yanis");
		expect(httpRequest.mock.calls.filter(([, url]) => String(url).endsWith("/me/friends")).length).toBe(loadsBefore + 1);
	});

	it("goes from the profile to the actions menu of the same friend", async () => {
		const wrapper = await mountPage();

		await wrapper.get("button.profile-btn").trigger("click");
		await flushPromises();
		await wrapper.get("[aria-labelledby=\"friend-profile-title\"] button.btn-ghost").trigger("click");

		expect(wrapper.find("[aria-labelledby=\"friend-profile-title\"]").exists()).toBe(false);
		expect(wrapper.get("#player-actions-title").text()).toBe("Yanis");
		expect(wrapper.text()).toContain("Retirer de mes amis");
	});

	it("closes the profile when the friend is no longer in the list", async () => {
		const wrapper = await mountPage();
		answer({ ...view, friends: [] });

		await wrapper.get("button.profile-btn").trigger("click");
		await flushPromises();

		expect(wrapper.find("[aria-labelledby=\"friend-profile-title\"]").exists()).toBe(false);
	});

	it("invites the player to add a first friend when the list is empty", async () => {
		answer({ ...view, friends: [] });
		const wrapper = await mountPage();

		await wrapper.get(".empty-state button").trigger("click");

		expect(wrapper.find("[aria-labelledby=\"add-friend-title\"]").exists()).toBe(true);
	});

	it("shows a lock instead of steps for a friend who hides them", async () => {
		const hidden = { ...view.friends[0], player: { ...view.friends[0].player, today: null } };
		answer({ ...view, friends: [hidden] });
		const wrapper = await mountPage();

		expect(wrapper.find(".meta-masked").exists()).toBe(true);
		expect(wrapper.find(".meta").exists()).toBe(false);
	});

	it("offers a search from enough friends, and filters by pseudo", async () => {
		const many: Friendship[] = Array.from({ length: FRIEND_SEARCH_FROM }, (_, index) => ({
			...view.friends[0],
			id: 100 + index,
			player: { ...view.friends[0].player, id: 100 + index, pseudo: index === 0 ? "Inès" : `Joueur ${index}` }
		}));
		answer({ ...view, friends: many });
		const wrapper = await mountPage();

		await wrapper.get(".friend-search").setValue("ines");

		expect(wrapper.findAll("button.profile-btn")).toHaveLength(1);
		expect(wrapper.get("button.profile-btn").text()).toContain("Inès");
	});

	it("hides the search for a short list", async () => {
		const wrapper = await mountPage();

		expect(wrapper.find(".friend-search").exists()).toBe(false);
	});

	it("clears a notice by itself after a few seconds", async () => {
		const wrapper = await mountPage();
		vi.useFakeTimers();

		try {
			await wrapper.get("button.avatar-btn[aria-label=\"Ajouter un ami\"]").trigger("click");
			wrapper.findComponent({ name: "AddFriendSheet" }).vm.$emit("added", "Invitation envoyée.");
			await wrapper.vm.$nextTick();

			expect(wrapper.text()).toContain("Invitation envoyée.");
			expect(wrapper.find("[aria-labelledby=\"add-friend-title\"]").exists()).toBe(false);

			vi.advanceTimersByTime(4000);
			await wrapper.vm.$nextTick();

			expect(wrapper.text()).not.toContain("Invitation envoyée.");
		} finally {
			vi.useRealTimers();
		}
	});

	it("sends a cheer from the profile and shows it as sent", async () => {
		const wrapper = await mountPage();
		const cheered = { ...view, friends: [{ ...view.friends[0], cheer: { sent: true, received: false } }] };

		await wrapper.get("button.profile-btn").trigger("click");
		await flushPromises();
		answer(cheered);
		await wrapper.get("button.cheer-btn").trigger("click");
		await flushPromises();

		const cheerCall = httpRequest.mock.calls.find(([method, url]) => method === "POST" && String(url).endsWith("/me/friends/12/cheer"));

		expect(cheerCall).toBeDefined();
		expect(wrapper.get("button.cheer-btn").text()).toBe("Coup de patte envoyé");
		expect(wrapper.text()).toContain("Coup de patte envoyé à Yanis !");
	});

	it("marks a friend who cheered the player in the list", async () => {
		answer({ ...view, friends: [{ ...view.friends[0], cheer: { sent: false, received: true } }] });
		const wrapper = await mountPage();

		expect(wrapper.find(".cheer-waiting").exists()).toBe(true);
	});
});
