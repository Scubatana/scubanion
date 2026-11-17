import { mountSuspended } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import FriendProfileSheet from "~/components/FriendProfileSheet.vue";
import type { Friendship } from "~/utils/friends";
import { formatCount } from "~/utils/labels";
import type { CompanionCatalog } from "~/utils/types";

const { httpRequest } = vi.hoisted(() => ({ httpRequest: vi.fn() }));

vi.mock("~/utils/http", () => ({ httpRequest }));

const catalog: CompanionCatalog = {
	categories: [],
	species: [{ id: "dog", label: "Chien", svg: "", anchors: {} }],
	colors: [],
	accessories: [
		{ id: "cap", label: "Casquette", category: "head", image: "/cap.png" },
		{ id: "paw_scarf", label: "Foulard de l'amitié", category: "neck", image: "/paw_scarf.png" },
		{ id: "paw_bucket_hat", label: "Bob à pattes", category: "hat", image: "/paw_bucket_hat.png" }
	]
};

function friendship(player: Partial<Friendship["player"]> = {}, cheer: Friendship["cheer"] = { sent: false, received: false, streak: { days: 0, record: 0 } }): Friendship {
	return {
		id: 12,
		since: "2026-10-13",
		cheer,
		player: {
			id: 3,
			pseudo: "Yanis",
			companion: { name: "Bulle", species: "dog", color: "sand", accessories: ["cap"], level: 3 },
			today: { date: "2026-11-14", steps: 4200, goal: 5000 },
			streak: { days: 6, active: true },
			...player
		}
	};
}

function mount(value: Friendship, extra: { cheering?: boolean; cheerError?: string; unlocked?: string[] } = {}) {
	return mountSuspended(FriendProfileSheet, {
		props: { friendship: value, cheering: false, cheerError: "", ...extra },
		global: { stubs: { teleport: true } }
	});
}

describe("FriendProfileSheet", () => {
	beforeEach(() => {
		httpRequest.mockReset();
		clearNuxtState();
		useCatalog().catalog.value = catalog;
	});

	it("shows the companion, level, shared steps and streak, without listing worn accessories", async () => {
		const wrapper = await mount(friendship());
		const text = wrapper.text();

		expect(text).toContain("Yanis");
		expect(text).toContain("Bulle · Chien");
		expect(text).toContain("Amis depuis le 13 octobre 2026");
		expect(text).toContain(formatCount(4200));
		expect(text).toContain(`pas sur ${formatCount(5000)}`);
		expect(text).toContain("jours d'objectif");
		expect(text).not.toContain("Accessoires portés");
		expect(text).not.toContain("ne pas tout partager");
	});

	it("says what is hidden when the friend does not share steps and streak", async () => {
		const wrapper = await mount(friendship({ today: null, streak: null }));
		const text = wrapper.text();

		expect(text).toContain("Pas masqués");
		expect(text).toContain("Objectifs masqués");
		expect(text).toContain("Yanis a choisi de ne pas tout partager.");
		expect(wrapper.find(".wave-bar").exists()).toBe(false);
	});

	it("handles a friend who has no companion yet", async () => {
		const wrapper = await mount(friendship({ companion: null }));

		expect(wrapper.text()).toContain("N'a pas encore choisi son compagnon");
		expect(wrapper.find(".showcase").exists()).toBe(false);
	});

	it("closes from the button, the backdrop and the Escape key, and asks for more actions", async () => {
		const wrapper = await mount(friendship());

		await wrapper.get("button[aria-label=\"Fermer la fiche\"]").trigger("click");
		await wrapper.get(".sheet-backdrop").trigger("click");
		window.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
		await wrapper.findAll("button.btn-ghost").find((button) => button.text() === "Plus d'actions")?.trigger("click");

		expect(wrapper.emitted("close")).toHaveLength(3);
		expect(wrapper.emitted("actions")).toHaveLength(1);
	});

	it("stops listening to Escape once closed", async () => {
		const wrapper = await mount(friendship());

		wrapper.unmount();
		window.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));

		expect(wrapper.emitted("close")).toBeUndefined();
	});

	it("sends a cheer, then says it was sent and keeps the button off until tomorrow", async () => {
		const ready = await mount(friendship());
		const button = ready.get("button.cheer-btn");

		expect(button.text()).toBe("Coup de patte");
		await button.trigger("click");
		expect(ready.emitted("cheer")).toHaveLength(1);

		const sent = await mount(friendship({}, { sent: true, received: false, streak: { days: 0, record: 0 } }));

		expect(sent.get("button.cheer-btn").text()).toBe("Coup de patte envoyé");
		expect(sent.get("button.cheer-btn").attributes("disabled")).toBeDefined();
		expect(sent.text()).toContain("Tu pourras lui en envoyer un autre demain.");
	});

	it("tells the player a friend cheered them and offers to answer", async () => {
		const wrapper = await mount(friendship({}, { sent: false, received: true, streak: { days: 0, record: 0 } }));

		expect(wrapper.text()).toContain("Yanis t'a envoyé un coup de patte aujourd'hui.");
		expect(wrapper.get("button.cheer-btn").text()).toBe("Répondre d'un coup de patte");
	});

	it("shows the sending state and the server's refusal", async () => {
		const wrapper = await mount(friendship(), { cheering: true, cheerError: "Tu as déjà envoyé un coup de patte à Yanis aujourd'hui." });

		expect(wrapper.get("button.cheer-btn").text()).toBe("Envoi…");
		expect(wrapper.get("button.cheer-btn").attributes("disabled")).toBeDefined();
		expect(wrapper.text()).toContain("Tu as déjà envoyé un coup de patte à Yanis aujourd'hui.");
	});

	it("shows the cheer streak and the record when it is higher", async () => {
		const wrapper = await mount(friendship({}, { sent: false, received: false, streak: { days: 4, record: 9 } }));

		expect(wrapper.get(".cheer-streak-info").text()).toContain("Série de coups de patte : 4 jours");
		expect(wrapper.get(".cheer-record").text()).toBe("Record : 9 jours");
		expect(wrapper.get(".cheer-streak-info .streak").attributes("aria-label")).toBe("Série de coups de patte de 4 jours");
	});

	it("hides the record while the streak is the record", async () => {
		const wrapper = await mount(friendship({}, { sent: false, received: false, streak: { days: 1, record: 1 } }));

		expect(wrapper.get(".cheer-streak-info").text()).toContain("Série de coups de patte : 1 jour");
		expect(wrapper.find(".cheer-record").exists()).toBe(false);
	});

	it("keeps only the record once a streak has stopped, without blaming anyone", async () => {
		const wrapper = await mount(friendship({}, { sent: false, received: false, streak: { days: 0, record: 9 } }));

		expect(wrapper.get(".cheer-streak-info").text()).toBe("Record de série : 9 jours. Un échange aujourd'hui en relance une.");
	});

	it("explains how to start a streak", async () => {
		const wrapper = await mount(friendship());

		expect(wrapper.get(".cheer-streak-info").text()).toBe("Échangez un coup de patte le même jour pour lancer une série.");
	});

	it("celebrates a milestone with the accessories both friends just won", async () => {
		const wrapper = await mount(friendship(), { unlocked: ["paw_scarf"] });
		const milestone = wrapper.get(".milestone");

		expect(milestone.attributes("role")).toBe("status");
		expect(milestone.text()).toContain("Palier de série atteint !");
		expect(milestone.text()).toContain("Yanis et toi gagnez :");
		expect(milestone.text()).toContain("Foulard de l'amitié");
		expect(milestone.get("a").attributes("href")).toBe("/compagnon");
		expect(milestone.get("a").text()).toBe("Le porter sur mon compagnon");
	});

	it("speaks of several accessories when a long streak unlocks more than one", async () => {
		const wrapper = await mount(friendship(), { unlocked: ["paw_scarf", "paw_bucket_hat"] });

		expect(wrapper.findAll(".milestone .showcase-item")).toHaveLength(2);
		expect(wrapper.get(".milestone a").text()).toBe("Les porter sur mon compagnon");
	});

	it("shows no milestone without a new accessory", async () => {
		const wrapper = await mount(friendship());

		expect(wrapper.find(".milestone").exists()).toBe(false);
	});
});
