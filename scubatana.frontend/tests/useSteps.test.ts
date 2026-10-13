import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { navigateTo } = vi.hoisted(() => ({ navigateTo: vi.fn() }));

const mocks = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	storage: new Map<string, string>(),
	available: true,
	granted: true,
	recorded: [] as { start: number; steps: number }[],
	emit: null as ((count: number) => void) | null,
	appListeners: {} as Record<string, () => void>,
	openSettings: vi.fn()
}));

vi.mock("~/utils/http", () => ({ httpRequest: mocks.httpRequest }));

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

vi.mock("@capacitor/app", () => ({
	App: {
		addListener: async (event: string, listener: () => void) => {
			mocks.appListeners[event] = listener;

			return { remove: async () => undefined };
		}
	}
}));

vi.mock("~/utils/stepCounter", () => ({
	stepSensorAvailable: async () => mocks.available,
	stepPermissionGranted: async () => mocks.granted,
	watchSteps: async (onCount: (count: number) => void) => {
		mocks.emit = onCount;

		return async () => {
			mocks.emit = null;
		};
	},
	readRecordedSteps: async () => mocks.recorded,
	openStepSettings: mocks.openSettings
}));

mockNuxtImport("navigateTo", () => navigateTo);

const lea = { id: 7, email: "lea@example.com", pseudo: "Lea", birth_date: "2004-05-12", gender: null, ambition: "fitness" as const, profile_completed: true, stats: { total_steps: 0, quests_done: 0, friends_count: 0 } };

function answerSyncWith(steps: number): void {
	mocks.httpRequest.mockResolvedValue({ status: 200, data: { today: { date: "2026-10-13", steps, goal: 5000 }, streak: { days: 0, active: false }, total_steps: steps } });
}

async function settle(): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, 0));
}

describe("useSteps", () => {
	beforeEach(async () => {
		await useSteps().reset();
		mocks.httpRequest.mockReset();
		navigateTo.mockReset();
		mocks.storage.clear();
		mocks.available = true;
		mocks.granted = true;
		mocks.recorded = [];
		clearNuxtState();
		usePlayer().player.value = { ...lea };
		usePlayer().today.value = { date: "2026-10-13", steps: 0, goal: 5000 };
		await useSession().open("jeton-1", true);
	});

	it("says so when the phone has no step sensor or the permission is refused", async () => {
		mocks.available = false;
		await useSteps().start(true);
		expect(useSteps().status.value).toBe("unavailable");

		mocks.available = true;
		mocks.granted = false;
		await useSteps().start(true);
		expect(useSteps().status.value).toBe("denied");
	});

	it("starts again by itself when the player comes back from the settings with the permission granted", async () => {
		mocks.granted = false;
		await useSteps().start(true);

		mocks.granted = true;
		mocks.appListeners.resume();
		await settle();

		expect(useSteps().status.value).toBe("tracking");
	});

	it("shows steps the moment they are walked and sends the total with the identifier of the phone", async () => {
		answerSyncWith(120);
		const steps = useSteps();
		await steps.start(true);

		mocks.emit?.(5000);
		await settle();
		mocks.emit?.(5120);
		await settle();

		const [method, url, , body] = mocks.httpRequest.mock.calls[0];
		expect(method).toBe("POST");
		expect(url).toMatch(/\/api\/me\/steps$/);
		expect(body.device_id).toMatch(/^[A-Za-z0-9-]{8,64}$/);
		expect(body.days).toHaveLength(1);
		expect(body.days[0].steps).toBe(120);
		expect(steps.displayedSteps.value).toBe(120);
	});

	it("keeps showing the steps while the server is unreachable and sends them when the app is left", async () => {
		mocks.httpRequest.mockRejectedValue(new TypeError("Failed to fetch"));
		const steps = useSteps();
		await steps.start(true);
		mocks.emit?.(100);
		await settle();
		mocks.emit?.(340);
		await settle();

		expect(steps.displayedSteps.value).toBe(240);

		answerSyncWith(240);
		mocks.appListeners.pause();
		await settle();

		expect(mocks.httpRequest.mock.calls.at(-1)?.[3].days[0].steps).toBe(240);
		expect(steps.displayedSteps.value).toBe(240);
	});

	it("catches up with the steps recorded while the app was closed when it opens", async () => {
		answerSyncWith(0);
		const steps = useSteps();
		await steps.start(true);
		await steps.reset();
		usePlayer().player.value = { ...lea };
		mocks.httpRequest.mockClear();
		mocks.recorded = [{ start: Date.now() + 60000, steps: 1800 }];
		answerSyncWith(1800);

		await steps.start(true);

		expect(mocks.httpRequest.mock.calls[0][3].days[0].steps).toBe(1800);
	});

	it("keeps one counter per player on a shared phone", async () => {
		answerSyncWith(0);
		const steps = useSteps();
		await steps.start(true);
		mocks.emit?.(100);
		await settle();
		await steps.reset();

		expect([...mocks.storage.keys()]).toContain("steps.ledger.7");

		usePlayer().player.value = { ...lea, id: 8 };
		await steps.start(true);
		mocks.emit?.(900);
		await settle();

		expect(steps.displayedSteps.value).toBe(0);
		expect([...mocks.storage.keys()]).toContain("steps.ledger.8");
	});
});
