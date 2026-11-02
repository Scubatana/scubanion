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

	it("parses stored consents safely and falls back to defaults on invalid JSON", () => {
		expect(parseConsents(null)).toEqual(defaultConsents());
		expect(parseConsents("pas-du-json")).toEqual({ steps: true, location: true });
		expect(parseConsents(JSON.stringify({ steps: false, location: true }))).toEqual({ steps: false, location: true });
	});

	it("persists revocable consents per player and cleans them up on discard", async () => {
		const { consents, ensureLoaded, setStepsConsent, setLocationConsent, discard } = useConsents();

		await setStepsConsent(false);
		await setLocationConsent(false);

		expect(consents.value).toEqual({ steps: false, location: false });
		expect(JSON.parse(mocks.storage.get("consents.7") ?? "{}")).toEqual({ steps: false, location: false });

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

		expect(await useConsents().ensureLoaded()).toEqual({ steps: false, location: false });

		await discard();
		expect(mocks.storage.has("consents.7")).toBe(false);
		expect(await ensureLoaded()).toEqual({ steps: true, location: true });
	});
});
