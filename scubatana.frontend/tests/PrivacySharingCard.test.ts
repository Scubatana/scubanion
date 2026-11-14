import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import PrivacySharingCard from "~/components/PrivacySharingCard.vue";
import type { Player } from "~/utils/types";

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

function player(privacy: Player["privacy"]): Player {
	return {
		id: 7,
		email: "lea@example.com",
		pseudo: "Lea",
		birth_date: "2004-05-12",
		gender: null,
		ambition: "fitness",
		profile_completed: true,
		privacy,
		stats: { total_steps: 1000, quests_done: 0, friends_count: 1 }
	};
}

function switches(wrapper: Awaited<ReturnType<typeof mountSuspended>>) {
	const [steps, streak] = wrapper.findAll("button[role=\"switch\"]");

	return { steps, streak };
}

describe("PrivacySharingCard", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true, 7);
	});

	it("renders nothing until the player is loaded", async () => {
		const wrapper = await mountSuspended(PrivacySharingCard);

		expect(wrapper.find("button[role=\"switch\"]").exists()).toBe(false);
	});

	it("shows both settings hidden by default when the player has none yet", async () => {
		usePlayer().player.value = player(undefined);
		const wrapper = await mountSuspended(PrivacySharingCard);
		const { steps, streak } = switches(wrapper);

		expect(steps.attributes("aria-checked")).toBe("false");
		expect(streak.attributes("aria-checked")).toBe("false");
	});

	it("saves one switch at a time and shows the state confirmed by the server", async () => {
		usePlayer().player.value = player({ share_steps: false, share_streak: true });
		httpRequest.mockResolvedValueOnce({ status: 200, data: { player: player({ share_steps: true, share_streak: true }) } });
		const wrapper = await mountSuspended(PrivacySharingCard);

		await switches(wrapper).steps.trigger("click");
		await flushPromises();

		const [method, url, , body] = httpRequest.mock.calls[0];
		expect(method).toBe("PUT");
		expect(url).toMatch(/\/api\/me\/privacy$/);
		expect(body).toEqual({ share_steps: true, share_streak: true });
		expect(switches(wrapper).steps.attributes("aria-checked")).toBe("true");
		expect(switches(wrapper).streak.attributes("aria-checked")).toBe("true");
		expect(wrapper.find(".form-error").exists()).toBe(false);
	});

	it("keeps the previous state and explains the refusal when the server says no", async () => {
		usePlayer().player.value = player({ share_steps: false, share_streak: false });
		httpRequest.mockResolvedValueOnce({ status: 429, data: { message: "Too Many Attempts." } });
		const wrapper = await mountSuspended(PrivacySharingCard);

		await switches(wrapper).streak.trigger("click");
		await flushPromises();

		expect(switches(wrapper).streak.attributes("aria-checked")).toBe("false");
		expect(wrapper.find(".form-error").text()).toBe("Trop de tentatives. Réessaie dans quelques minutes.");
		expect(switches(wrapper).streak.attributes("disabled")).toBeUndefined();
	});

	it("ignores a second click while the first change is still being saved", async () => {
		usePlayer().player.value = player({ share_steps: false, share_streak: false });
		let answer: (value: unknown) => void = () => undefined;
		httpRequest.mockReturnValueOnce(new Promise((resolve) => {
			answer = resolve;
		}));
		const wrapper = await mountSuspended(PrivacySharingCard);

		await switches(wrapper).steps.trigger("click");
		expect(switches(wrapper).steps.attributes("disabled")).toBeDefined();
		await switches(wrapper).streak.trigger("click");

		answer({ status: 200, data: { player: player({ share_steps: true, share_streak: false }) } });
		await flushPromises();

		expect(httpRequest).toHaveBeenCalledTimes(1);
		expect(switches(wrapper).steps.attributes("aria-checked")).toBe("true");
	});
});
