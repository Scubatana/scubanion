import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	allowed: vi.fn(),
	current: vi.fn(),
	listeners: new Map<string, () => void>(),
	storage: new Map<string, string>()
}));

vi.mock("~/utils/http", () => ({ httpRequest: mocks.httpRequest }));

vi.mock("~/utils/geolocation", () => ({ positionAllowed: mocks.allowed, currentPosition: mocks.current }));

vi.mock("@capacitor/app", () => ({
	App: {
		addListener: async (event: string, listener: () => void) => {
			mocks.listeners.set(event, listener);
			return { remove: async () => undefined };
		}
	}
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

mockNuxtImport("navigateTo", () => mocks.navigateTo);

const fix = { latitude: 43.6953, longitude: 7.2806, accuracy_m: 12, mocked: true, recorded_at: "2026-10-13T18:00:00.000Z" };

describe("usePosition", () => {
	beforeEach(async () => {
		vi.useFakeTimers();
		mocks.httpRequest.mockReset();
		mocks.allowed.mockReset();
		mocks.current.mockReset();
		mocks.storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true);
		mocks.httpRequest.mockResolvedValue({ status: 204, data: null });
		mocks.current.mockResolvedValue(fix);
	});

	afterEach(() => {
		usePosition().stop();
		vi.useRealTimers();
	});

	it("sends the position with its accuracy and mocked flag, then every minute", async () => {
		mocks.allowed.mockResolvedValue(true);

		await usePosition().start();

		expect(mocks.httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/position$/);
		expect(mocks.httpRequest.mock.calls[0][3]).toEqual(fix);

		await vi.advanceTimersByTimeAsync(60000);

		expect(mocks.httpRequest).toHaveBeenCalledTimes(2);
		expect(mocks.current).toHaveBeenCalledWith(false);
	});

	it("sends nothing and never asks for the permission when it was not granted", async () => {
		mocks.allowed.mockResolvedValue(false);

		await usePosition().start();
		await vi.advanceTimersByTimeAsync(120000);

		expect(mocks.allowed).toHaveBeenCalledWith(false);
		expect(mocks.httpRequest).not.toHaveBeenCalled();
	});

	it("stops while the app is in the background and starts again when it comes back", async () => {
		mocks.allowed.mockResolvedValue(true);
		await usePosition().start();

		mocks.listeners.get("pause")?.();
		await vi.advanceTimersByTimeAsync(180000);

		expect(mocks.httpRequest).toHaveBeenCalledTimes(1);

		mocks.listeners.get("resume")?.();
		await vi.advanceTimersByTimeAsync(0);

		expect(mocks.httpRequest).toHaveBeenCalledTimes(2);
	});

	it("refreshes the quests already shown, so a place reached is seen as validated", async () => {
		mocks.allowed.mockResolvedValue(true);
		useQuests().quests.value = [{ id: 1, quest_id: 1, name: "Juste ici", description: null, icon: null, period: "once", expires_at: null, status: "active", step: 1, steps: 1, tasks: [], step_rewards: [], quest_rewards: [], rewards_to_claim: [] }];
		mocks.httpRequest.mockResolvedValueOnce({ status: 204, data: null });
		mocks.httpRequest.mockResolvedValueOnce({ status: 200, data: { quests: [] } });

		await usePosition().start();

		expect(mocks.httpRequest.mock.calls[1][0]).toBe("GET");
		expect(mocks.httpRequest.mock.calls[1][1]).toMatch(/\/api\/me\/quests$/);
		expect(useQuests().quests.value).toEqual([]);
	});
});
