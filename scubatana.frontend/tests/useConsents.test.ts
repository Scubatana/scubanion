import { beforeEach, describe, expect, it, vi } from "vitest";
import { defaultConsents, parseConsents } from "~/utils/consents";

const mocks = vi.hoisted(() => ({
	storage: new Map<string, string>(),
	stepsStart: vi.fn(),
	stepsReset: vi.fn(),
	positionStart: vi.fn(),
	positionStop: vi.fn()
}));

vi.mock("@capacitor/preferences", () => ({
	Preferences: {
		get: async ({ key }: { key: string }) => ({ value: mocks.storage.get(key) ?? null }),
		set: async ({ key, value }: { key: string; value: string }) => {
			mocks.storage.set(key, value);
		},
		remove: async ({ key }: { key: string }) => {
			mocks.storage.delete(key);
		}
	}
}));

describe("useConsents", () => {
	beforeEach(() => {
		mocks.storage.clear();
		clearNuxtState();
		usePlayer().player.value = {
			id: 7,
			email: "lea@example.com",
			pseudo: "Lea",
			birth_date: "2004-05-12",
			gender: null,
			ambition: "fitness",
			profile_completed: true,
			stats: { total_steps: 0, quests_done: 0, friends_count: 0 }
		};
	});

	it("parses stored consents safely and falls back to false defaults on invalid JSON", () => {
		expect(defaultConsents()).toEqual({ steps: false, location: false });
		expect(parseConsents(null)).toEqual({ steps: false, location: false });
		expect(parseConsents("pas-du-json")).toEqual({ steps: false, location: false });
		expect(parseConsents(JSON.stringify({ steps: true, location: false }))).toEqual({ steps: true, location: false });
	});

	it("persists revocable consents per player and cleans them up on discard", async () => {
		const { consents, ensureLoaded, setStepsConsent, setLocationConsent, discard } = useConsents();

		await setStepsConsent(true);
		await setLocationConsent(true);

		expect(consents.value).toEqual({ steps: true, location: true });
		expect(JSON.parse(mocks.storage.get("consents.7") ?? "{}")).toEqual({ steps: true, location: true });

		clearNuxtState();
		usePlayer().player.value = {
			id: 7,
			email: "lea@example.com",
			pseudo: "Lea",
			birth_date: "2004-05-12",
			gender: null,
			ambition: "fitness",
			profile_completed: true,
			stats: { total_steps: 0, quests_done: 0, friends_count: 0 }
		};

		expect(await useConsents().ensureLoaded()).toEqual({ steps: true, location: true });

		await discard();
		expect(mocks.storage.has("consents.7")).toBe(false);
		expect(await ensureLoaded()).toEqual({ steps: false, location: false });
	});

	it("finds the consents of the session player when the profile cannot be loaded offline", async () => {
		mocks.storage.set("consents.7", JSON.stringify({ steps: true, location: false }));
		await useSession().open("jeton-1", true, 7);
		usePlayer().player.value = null;

		expect(await useConsents().ensureLoaded()).toEqual({ steps: true, location: false });
	});
});
