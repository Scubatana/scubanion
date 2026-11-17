import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { enableAutoUnmount, flushPromises } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AddFriendSheet from "~/components/AddFriendSheet.vue";
import type { FriendsView } from "~/utils/friends";

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

// Démonter avant que le test suivant vide l'état partagé.
enableAutoUnmount(afterEach);

const empty: FriendsView = { code: "K7Q2MZ4P", friends: [], received: [], sent: [] };

function mount() {
	return mountSuspended(AddFriendSheet, { global: { stubs: { teleport: true } } });
}

describe("AddFriendSheet", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true, 7);
		// Sans joueur connecté, useFriends remet son état à zéro.
		usePlayer().player.value = { id: 7, email: "lea@example.com", pseudo: "Lea", birth_date: "2004-05-12", gender: null, ambition: "fitness", profile_completed: true, stats: { total_steps: 0, quests_done: 0, friends_count: 0 } };
		useFriends().code.value = "K7Q2MZ4P";
	});

	it("shows the player's own code, formatted", async () => {
		const wrapper = await mount();

		expect(wrapper.text()).toContain("K7Q2-MZ4P");
	});

	it("sends the invite and tells the page what happened", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { ...empty, sent: [{ id: 14, sent_at: "2026-11-14T10:00:00+01:00", player: { id: 5, pseudo: "Tom" } }] } });
		const wrapper = await mount();

		await wrapper.get("#friend-code-input").setValue("k7q2 mz4p");
		await wrapper.get("form").trigger("submit");
		await flushPromises();

		expect(httpRequest.mock.calls[0][0]).toBe("POST");
		expect(wrapper.emitted("added")).toEqual([["Invitation envoyée."]]);
	});

	it("keeps the sheet open with the server's message when the code is wrong", async () => {
		httpRequest.mockResolvedValue({ status: 422, data: { message: "Code inconnu.", errors: { code: ["Aucun joueur n'a ce code."] } } });
		const wrapper = await mount();

		await wrapper.get("#friend-code-input").setValue("ZZZZZZZZ");
		await wrapper.get("form").trigger("submit");
		await flushPromises();

		expect(wrapper.emitted("added")).toBeUndefined();
		expect(wrapper.get("[role=\"alert\"]").text()).toBe("Aucun joueur n'a ce code.");
	});

	it("asks the page to show the player's QR", async () => {
		const wrapper = await mount();
		const showQr = wrapper.findAll("button").find((button) => button.text() === "Afficher mon QR");

		await showQr?.trigger("click");

		expect(wrapper.emitted("showQr")).toHaveLength(1);
	});
});
