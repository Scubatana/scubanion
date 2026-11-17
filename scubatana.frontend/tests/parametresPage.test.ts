import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { flushPromises } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import ParametresPage from "~/pages/parametres.vue";
import type { Player } from "~/utils/types";

const { httpRequest, navigateTo, storage, steps, position } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>(),
	steps: { start: vi.fn(), reset: vi.fn(), discard: vi.fn(), halt: vi.fn(), settled: vi.fn() },
	position: { start: vi.fn(), stop: vi.fn() }
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

// Le podomètre et le GPS sont natifs : ils sont remplacés par des doubles.
mockNuxtImport("useSteps", () => () => steps);

mockNuxtImport("usePosition", () => () => position);

const lea: Player = {
	id: 7,
	email: "lea@example.com",
	pseudo: "Lea",
	birth_date: "2004-05-12",
	gender: null,
	ambition: "fitness",
	profile_completed: true,
	privacy: { share_steps: false, share_streak: false },
	stats: { total_steps: 1200, quests_done: 0, friends_count: 0 }
};

const me = { player: lea, companion: null, today: { date: "2026-11-17", steps: 1200, goal: 5000 }, streak: { days: 0, active: false } };

function calls(): Array<[string, string, unknown]> {
	return httpRequest.mock.calls.map(([method, url, , body]) => [method, String(url).replace(/^.*\/api/, ""), body]);
}

function storedConsents(): unknown {
	return JSON.parse(storage.get("consents.7") ?? "null");
}

function consentSwitch(wrapper: Awaited<ReturnType<typeof mountSuspended>>, label: string) {
	const found = wrapper.findAll("button[role=\"switch\"]").find((button) => button.attributes("aria-label") === label);

	if (found === undefined) {
		throw new Error(`Interrupteur introuvable : ${label}`);
	}

	return found;
}


const STEPS = "Autoriser le suivi de l'activité physique";

const LOCATION = "Autoriser l'utilisation de la position géographique";

// Chaque page montée est démontée après son test : sinon, clearNuxtState() du test suivant vide
// l'état qu'elle affiche encore, et son rendu plante (erreur non rattrapée qui fait échouer Vitest).
const mounted: Array<{ unmount: () => void }> = [];

async function mountPage(page: Parameters<typeof mountSuspended>[0]) {
	const wrapper = await mountSuspended(page);
	mounted.push(wrapper);

	return wrapper;
}

afterEach(() => {
	mounted.splice(0).forEach((wrapper) => wrapper.unmount());
});

describe("page Paramètres", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		Object.values(steps).forEach((spy) => spy.mockReset());
		Object.values(position).forEach((spy) => spy.mockReset());
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-7", true, 7);
		httpRequest.mockImplementation(async (method: string, url: string) => (method === "GET" && url.endsWith("/api/me") ? { status: 200, data: me } : { status: 404, data: { message: "Introuvable" } }));
	});

	it("shows both consents off until the player gives them", async () => {
		const wrapper = await mountPage(ParametresPage);
		await flushPromises();

		expect(consentSwitch(wrapper, STEPS).attributes("aria-checked")).toBe("false");
		expect(consentSwitch(wrapper, LOCATION).attributes("aria-checked")).toBe("false");
		expect(wrapper.find(".goal-value").text()).toBe("5 000");
	});

	it("lets the player give the location consent, then withdraw it", async () => {
		const wrapper = await mountPage(ParametresPage);
		await flushPromises();

		await consentSwitch(wrapper, LOCATION).trigger("click");
		await flushPromises();

		expect(consentSwitch(wrapper, LOCATION).attributes("aria-checked")).toBe("true");
		expect(storedConsents()).toEqual({ steps: false, location: true });

		await consentSwitch(wrapper, LOCATION).trigger("click");
		await flushPromises();

		expect(consentSwitch(wrapper, LOCATION).attributes("aria-checked")).toBe("false");
		expect(storedConsents()).toEqual({ steps: false, location: false });
	});

	it("lets the player withdraw the activity consent given before", async () => {
		storage.set("consents.7", JSON.stringify({ steps: true, location: true }));
		const wrapper = await mountPage(ParametresPage);
		await flushPromises();

		await consentSwitch(wrapper, STEPS).trigger("click");
		await flushPromises();

		expect(consentSwitch(wrapper, STEPS).attributes("aria-checked")).toBe("false");
		expect(storedConsents()).toEqual({ steps: false, location: true });
	});

	it("keeps the consents given on this phone for the next visit", async () => {
		storage.set("consents.7", JSON.stringify({ steps: true, location: false }));

		const wrapper = await mountPage(ParametresPage);
		await flushPromises();

		expect(consentSwitch(wrapper, STEPS).attributes("aria-checked")).toBe("true");
		expect(consentSwitch(wrapper, LOCATION).attributes("aria-checked")).toBe("false");
	});

	it("raises the daily goal by 500 steps", async () => {
		const wrapper = await mountPage(ParametresPage);
		await flushPromises();
		httpRequest.mockResolvedValueOnce({ status: 200, data: { today: { date: "2026-11-17", steps: 1200, goal: 5500 }, streak: { days: 0, active: false } } });

		await wrapper.findAll("button").find((button) => button.attributes("aria-label") === "Augmenter l'objectif")!.trigger("click");
		await flushPromises();

		expect(calls().at(-1)).toEqual(["PUT", "/me/goal", { daily_step_goal: 5500 }]);
		expect(wrapper.find(".goal-value").text()).toBe("5 500");
	});

	it("deletes the account only after a confirmation, then goes back to the login page", async () => {
		const wrapper = await mountPage(ParametresPage);
		await flushPromises();
		httpRequest.mockResolvedValueOnce({ status: 204, data: null });

		const ask = wrapper.findAll("button").find((button) => button.text() === "Supprimer mon compte");
		await ask?.trigger("click");

		expect(calls().some(([method]) => method === "DELETE")).toBe(false);

		const confirm = wrapper.findAll("button").find((button) => button.text() === "Supprimer définitivement");
		await confirm?.trigger("click");
		await flushPromises();

		expect(calls().at(-1)).toEqual(["DELETE", "/me?confirm=1", undefined]);
		expect(steps.discard).toHaveBeenCalled();
		expect(useSession().token.value).toBeNull();
		expect(navigateTo).toHaveBeenCalledWith("/login", { replace: true });
	});

	it("keeps the account and explains why when the deletion fails", async () => {
		const wrapper = await mountPage(ParametresPage);
		await flushPromises();
		httpRequest.mockResolvedValueOnce({ status: 500, data: { message: "Server Error" } });

		await wrapper.findAll("button").find((button) => button.text() === "Supprimer mon compte")?.trigger("click");
		await wrapper.findAll("button").find((button) => button.text() === "Supprimer définitivement")?.trigger("click");
		await flushPromises();

		expect(wrapper.find(".btn-danger + .btn-ghost + .form-error, .form-error").exists()).toBe(true);
		expect(useSession().token.value).toBe("jeton-7");
		expect(navigateTo).not.toHaveBeenCalled();
	});
});
