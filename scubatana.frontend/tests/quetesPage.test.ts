import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import QuetesPage from "~/pages/quetes.vue";
import type { PlayerQuest, QuestTask } from "~/utils/types";

const { httpRequest, navigateTo, storage, settled, currentPosition } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>(),
	settled: vi.fn(),
	currentPosition: vi.fn()
}));

vi.mock("~/utils/http", () => ({ httpRequest }));

vi.mock("~/utils/geolocation", async (importOriginal) => ({
	...(await importOriginal<typeof import("~/utils/geolocation")>()),
	currentPosition
}));

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

const catalog = { categories: [], species: [], colors: [], accessories: [{ id: "crown", label: "Couronne", category: "head", image: "/crown.svg", earnable: true }] };

function task(overrides: Partial<QuestTask>): QuestTask {
	return { id: 10, type: "visit_place", action: "checkin", description: "Rejoins la Colline du Château", target: 1, value: 0, completed: false, place: null, place_type: null, questions: null, rewards: [], ...overrides };
}

function quest(overrides: Partial<PlayerQuest>): PlayerQuest {
	return { id: 4, quest_id: 1, name: "Le belvédère", description: "Monte jusqu'à la colline.", icon: null, period: "once", expires_at: null, status: "active", step: 1, steps: 2, tasks: [task({})], step_rewards: [], quest_rewards: [{ xp: 200, accessory: "crown" }], rewards_to_claim: [], ...overrides };
}

type Route = (body: unknown) => { status: number; data: unknown };

// Répond selon la route appelée, comme l'API ; les routes non prévues échouent en 404.
function api(routes: Record<string, Route>): void {
	httpRequest.mockImplementation(async (method: string, url: string, _headers: unknown, body: unknown) => {
		const route = routes[`${method} ${String(url).replace(/^.*\/api/, "")}`];

		return route ? route(body) : { status: 404, data: { message: "Introuvable" } };
	});
}

function sent(method: string, path: string): unknown[] {
	return httpRequest.mock.calls.filter(([verb, url]) => verb === method && String(url).endsWith(`/api${path}`)).map(([, , , body]) => body);
}

function button(wrapper: Awaited<ReturnType<typeof mountSuspended>>, label: string) {
	const found = wrapper.findAll("button").find((candidate) => candidate.text() === label);

	if (found === undefined) {
		throw new Error(`Bouton introuvable : ${label}`);
	}

	return found;
}

describe("page Quêtes", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		settled.mockReset().mockResolvedValue(undefined);
		currentPosition.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-7", true, 7);
	});

	it("shows each quest with its step, its task and its rewards", async () => {
		api({ "GET /me/quests": () => ({ status: 200, data: { quests: [quest({})] } }), "GET /companion/catalog": () => ({ status: 200, data: catalog }) });

		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		expect(wrapper.text()).toContain("Le belvédère");
		expect(wrapper.text()).toContain("Étape 1 sur 2");
		expect(wrapper.text()).toContain("Rejoins la Colline du Château");
		expect(wrapper.text()).toContain("Fin de la quête : 200 XP + Couronne");
	});

	it("says when there is no quest", async () => {
		api({ "GET /me/quests": () => ({ status: 200, data: { quests: [] } }), "GET /companion/catalog": () => ({ status: 200, data: catalog }) });

		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		expect(wrapper.text()).toContain("Aucune quête pour le moment.");
	});

	it("validates a place with the current position", async () => {
		currentPosition.mockResolvedValue({ latitude: 43.6953, longitude: 7.2806, accuracy_m: 12, mocked: false, recorded_at: "2026-11-17T14:00:00.000Z" });
		api({
			"GET /me/quests": () => ({ status: 200, data: { quests: [quest({})] } }),
			"GET /companion/catalog": () => ({ status: 200, data: catalog }),
			"POST /me/quests/4/tasks/10": () => ({ status: 200, data: { status: "active", quest: quest({ step: 2, tasks: [task({ id: 11, type: "quiz", action: "answer", description: "Réponds au quiz" })] }) } })
		});
		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		await button(wrapper, "Je suis sur place").trigger("click");
		await flushPromises();

		expect(currentPosition).toHaveBeenCalledWith(true);
		expect(sent("POST", "/me/quests/4/tasks/10")[0]).toMatchObject({ latitude: 43.6953, longitude: 7.2806, accuracy_m: 12, mocked: false });
		expect(wrapper.text()).toContain("Étape 2 sur 2");
	});

	it("shows why a place is refused", async () => {
		currentPosition.mockResolvedValue({ latitude: 43.7102, longitude: 7.262, accuracy_m: 12, mocked: false, recorded_at: "2026-11-17T14:00:00.000Z" });
		api({
			"GET /me/quests": () => ({ status: 200, data: { quests: [quest({})] } }),
			"GET /companion/catalog": () => ({ status: 200, data: catalog }),
			"POST /me/quests/4/tasks/10": () => ({ status: 422, data: { message: "Erreur", errors: { position: ["Tu n'es pas encore sur place."] } } })
		});
		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		await button(wrapper, "Je suis sur place").trigger("click");
		await flushPromises();

		expect(wrapper.find(".form-error").text()).toBe("Tu n'es pas encore sur place.");
	});

	it("sends the code typed for a scan task", async () => {
		api({
			"GET /me/quests": () => ({ status: 200, data: { quests: [quest({ tasks: [task({ type: "scan_code", action: "scan", description: "Scanne le code du comptoir" })] })] } }),
			"GET /companion/catalog": () => ({ status: 200, data: catalog }),
			"POST /me/quests/4/tasks/10": () => ({ status: 422, data: { message: "Erreur", errors: { code: ["Ce code ne correspond pas."] } } })
		});
		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		await wrapper.find("input[placeholder=Code]").setValue("SCUBA-THE");
		await button(wrapper, "Valider le code").trigger("click");
		await flushPromises();

		expect(sent("POST", "/me/quests/4/tasks/10")[0]).toMatchObject({ code: "SCUBA-THE" });
		expect(wrapper.find(".form-error").text()).toBe("Ce code ne correspond pas.");
	});

	it("answers a quiz question and announces a failed quest", async () => {
		const quiz = task({ type: "quiz", action: "answer", description: "Réponds au quiz", target: 2, questions: [{ question: "Quelle mer vois-tu ?", choices: ["La Manche", "La Méditerranée"] }, { question: "Et ensuite ?", choices: ["A", "B"] }] });
		api({
			"GET /me/quests": () => ({ status: 200, data: { quests: [quest({ tasks: [quiz] })] } }),
			"GET /companion/catalog": () => ({ status: 200, data: catalog }),
			"POST /me/quests/4/tasks/10": () => ({ status: 200, data: { status: "failed", quest: null } })
		});
		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		expect(wrapper.text()).toContain("Quelle mer vois-tu ?");

		await button(wrapper, "La Manche").trigger("click");
		await flushPromises();

		expect(sent("POST", "/me/quests/4/tasks/10")[0]).toMatchObject({ question: 0, choice: 0 });
		expect(wrapper.text()).toContain("Mauvaise réponse : la quête « Le belvédère » est ratée.");
		expect(wrapper.text()).not.toContain("Quelle mer vois-tu ?");
	});

	it("collects the rewards of a finished quest", async () => {
		api({
			"GET /me/quests": () => ({ status: 200, data: { quests: [quest({ status: "completed", tasks: [], quest_rewards: [], rewards_to_claim: [{ id: 3, xp: 200, accessory: "crown" }] })] } }),
			"GET /companion/catalog": () => ({ status: 200, data: catalog }),
			"POST /me/quests/4/claim": () => ({ status: 200, data: { claimed: [{ id: 3, xp: 200, accessory: "crown" }], quest: null, companion: null } })
		});
		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		await button(wrapper, "Récupérer : 200 XP + Couronne").trigger("click");
		await flushPromises();

		expect(sent("POST", "/me/quests/4/claim")).toHaveLength(1);
		expect(wrapper.text()).toContain("Tu as gagné 200 XP + Couronne !");
		expect(wrapper.text()).not.toContain("Le belvédère");
	});

	it("offers to try again when the quests cannot be loaded", async () => {
		api({ "GET /companion/catalog": () => ({ status: 200, data: catalog }) });
		httpRequest.mockRejectedValueOnce(new Error("offline"));

		const wrapper = await mountSuspended(QuetesPage);
		await flushPromises();

		expect(wrapper.find(".form-error").exists()).toBe(true);
		expect(button(wrapper, "Réessayer").exists()).toBe(true);
	});
});
