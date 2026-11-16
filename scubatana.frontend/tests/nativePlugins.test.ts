import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { currentPosition, LOCATION_REQUIRED, openLocationSettings, POSITION_UNAVAILABLE, positionAccess } from "~/utils/geolocation";
import { httpRequest, parseBody } from "~/utils/http";
import { nfcStatus, watchNfcTags } from "~/utils/nfc";
import { openStepSettings, readRecordedSteps, stepPermissionGranted, stepSensorAvailable, watchSteps } from "~/utils/stepCounter";

// Les plugins natifs (podomètre, position, NFC) et CapacitorHttp sont remplacés par des doubles :
// chaque test choisit la plateforme (Android ou navigateur) et ce que répond le plugin.
const { platform, plugins, nativeHttp } = vi.hoisted(() => ({
	platform: { native: true },
	plugins: {
		StepCounter: {
			isAvailable: vi.fn(),
			checkPermissions: vi.fn(),
			requestPermissions: vi.fn(),
			start: vi.fn(),
			stop: vi.fn(),
			startRecording: vi.fn(),
			readRecorded: vi.fn(),
			openSettings: vi.fn(),
			addListener: vi.fn()
		},
		Position: {
			checkPermissions: vi.fn(),
			requestPermissions: vi.fn(),
			current: vi.fn(),
			openSettings: vi.fn()
		},
		Nfc: {
			isAvailable: vi.fn(),
			takePending: vi.fn(),
			addListener: vi.fn()
		}
	} as Record<string, Record<string, ReturnType<typeof vi.fn>>>,
	nativeHttp: { request: vi.fn() }
}));

vi.mock("@capacitor/core", () => ({
	Capacitor: { isNativePlatform: () => platform.native },
	CapacitorHttp: nativeHttp,
	registerPlugin: (name: string) => plugins[name]
}));

function resetPlugins(): void {
	for (const plugin of Object.values(plugins)) {
		for (const method of Object.values(plugin)) {
			method.mockReset();
		}
	}

	nativeHttp.request.mockReset();
}

describe("native step counter (stepCounter)", () => {
	beforeEach(() => {
		platform.native = true;
		resetPlugins();
	});

	it("never asks the plugin in a browser, and relays its answer on Android", async () => {
		platform.native = false;
		expect(await stepSensorAvailable()).toBe(false);
		expect(plugins.StepCounter.isAvailable).not.toHaveBeenCalled();

		platform.native = true;
		plugins.StepCounter.isAvailable.mockResolvedValue({ available: true });
		expect(await stepSensorAvailable()).toBe(true);
	});

	it("only asks for the physical activity permission when told to", async () => {
		plugins.StepCounter.checkPermissions.mockResolvedValue({ activity: "granted" });
		expect(await stepPermissionGranted(true)).toBe(true);
		expect(plugins.StepCounter.requestPermissions).not.toHaveBeenCalled();

		plugins.StepCounter.checkPermissions.mockResolvedValue({ activity: "prompt" });
		expect(await stepPermissionGranted(false)).toBe(false);
		expect(plugins.StepCounter.requestPermissions).not.toHaveBeenCalled();

		plugins.StepCounter.requestPermissions.mockResolvedValue({ activity: "granted" });
		expect(await stepPermissionGranted(true)).toBe(true);

		plugins.StepCounter.requestPermissions.mockResolvedValue({ activity: "denied" });
		expect(await stepPermissionGranted(true)).toBe(false);
	});

	it("forwards every sensor reading, then unsubscribes and stops the sensor", async () => {
		let listener: (event: { count: number }) => void = () => undefined;
		const remove = vi.fn();
		plugins.StepCounter.addListener.mockImplementation(async (_event: string, callback: typeof listener) => {
			listener = callback;
			return { remove };
		});

		const counts: number[] = [];
		const stop = await watchSteps((count) => counts.push(count));

		expect(plugins.StepCounter.start).toHaveBeenCalledOnce();
		listener({ count: 1200 });
		listener({ count: 1250 });
		expect(counts).toEqual([1200, 1250]);

		await stop();
		expect(remove).toHaveBeenCalledOnce();
		expect(plugins.StepCounter.stop).toHaveBeenCalledOnce();
	});

	it("starts recording before reading the steps walked while the app was closed", async () => {
		const buckets = [{ start: 1, end: 2, steps: 300 }];
		plugins.StepCounter.readRecorded.mockResolvedValue({ buckets });

		expect(await readRecordedSteps(1_700_000_000_000)).toEqual(buckets);
		expect(plugins.StepCounter.startRecording).toHaveBeenCalledOnce();
		expect(plugins.StepCounter.readRecorded).toHaveBeenCalledWith({ from: 1_700_000_000_000 });

		await openStepSettings();
		expect(plugins.StepCounter.openSettings).toHaveBeenCalledOnce();
	});
});

describe("native position (geolocation)", () => {
	beforeEach(() => {
		platform.native = true;
		resetPlugins();
	});

	it("answers unsupported in a browser without touching the plugin", async () => {
		platform.native = false;

		expect(await positionAccess(true)).toBe("unsupported");
		expect(plugins.Position.checkPermissions).not.toHaveBeenCalled();
	});

	it("only asks for the location permission when told to", async () => {
		plugins.Position.checkPermissions.mockResolvedValue({ location: "prompt" });
		expect(await positionAccess(false)).toBe("denied");
		expect(plugins.Position.requestPermissions).not.toHaveBeenCalled();

		plugins.Position.requestPermissions.mockResolvedValue({ location: "granted" });
		expect(await positionAccess(true)).toBe("granted");
	});

	it("returns the position in the API format, or a readable message when it is missing", async () => {
		plugins.Position.checkPermissions.mockResolvedValue({ location: "granted" });
		plugins.Position.current.mockResolvedValue({ latitude: 43.7, longitude: 7.28, accuracy: 8.4, mocked: false, time: Date.UTC(2026, 10, 14, 9, 0) });

		await expect(currentPosition(false)).resolves.toEqual({
			latitude: 43.7,
			longitude: 7.28,
			accuracy_m: 8,
			mocked: false,
			recorded_at: "2026-11-14T09:00:00.000Z"
		});

		plugins.Position.current.mockRejectedValue(new Error("timeout"));
		await expect(currentPosition(false)).rejects.toThrow(POSITION_UNAVAILABLE);

		plugins.Position.checkPermissions.mockResolvedValue({ location: "denied" });
		await expect(currentPosition(false)).rejects.toThrow(LOCATION_REQUIRED);
		expect(plugins.Position.current).toHaveBeenCalledTimes(2);

		await openLocationSettings();
		expect(plugins.Position.openSettings).toHaveBeenCalledOnce();
	});
});

describe("native NFC reading (nfc)", () => {
	beforeEach(() => {
		platform.native = true;
		resetPlugins();
	});

	it("reports no NFC in a browser, and relays the phone state on Android", async () => {
		platform.native = false;
		expect(await nfcStatus()).toEqual({ available: false, enabled: false });

		platform.native = true;
		plugins.Nfc.isAvailable.mockResolvedValue({ available: true, enabled: false });
		expect(await nfcStatus()).toEqual({ available: true, enabled: false });
	});

	it("listens to nothing in a browser", async () => {
		platform.native = false;
		const stop = await watchNfcTags(() => undefined);

		await expect(stop()).resolves.toBeUndefined();
		expect(plugins.Nfc.addListener).not.toHaveBeenCalled();
	});

	it("handles the tag that opened the app, then every tag read afterwards, and ignores empty events", async () => {
		let listener: (event: { url?: string } | undefined) => void = () => undefined;
		const remove = vi.fn();
		plugins.Nfc.addListener.mockImplementation(async (_event: string, callback: typeof listener) => {
			listener = callback;
			return { remove };
		});
		plugins.Nfc.takePending.mockResolvedValue({ url: "https://scuba.picsou06.fr/b/au-lancement" });

		const urls: string[] = [];
		const stop = await watchNfcTags((url) => urls.push(url));

		listener({ url: "https://scuba.picsou06.fr/b/ensuite" });
		listener(undefined);
		listener({});
		expect(urls).toEqual(["https://scuba.picsou06.fr/b/au-lancement", "https://scuba.picsou06.fr/b/ensuite"]);

		await stop();
		expect(remove).toHaveBeenCalledOnce();
	});

	it("handles nothing at launch when the app was not opened by a tag", async () => {
		plugins.Nfc.addListener.mockResolvedValue({ remove: vi.fn() });
		plugins.Nfc.takePending.mockResolvedValue({ url: null });

		const urls: string[] = [];
		await watchNfcTags((url) => urls.push(url));

		expect(urls).toEqual([]);
	});
});

describe("HTTP requests (http)", () => {
	const fetchMock = vi.fn();

	beforeEach(() => {
		resetPlugins();
		fetchMock.mockReset();
		vi.stubGlobal("fetch", fetchMock);
	});

	afterEach(() => {
		vi.unstubAllGlobals();
	});

	it("reads a response body whether it is decoded, empty, JSON or unreadable", () => {
		expect(parseBody({ ok: true })).toEqual({ ok: true });
		expect(parseBody(undefined)).toBeNull();
		expect(parseBody("")).toBeNull();
		expect(parseBody("{\"steps\":4200}")).toEqual({ steps: 4200 });
		expect(parseBody("<html>502</html>")).toBeNull();
	});

	it("goes through CapacitorHttp on Android, without CORS or fetch", async () => {
		platform.native = true;
		nativeHttp.request.mockResolvedValue({ status: 201, data: "{\"id\":7}" });

		const response = await httpRequest("POST", "https://api.test/api/me/steps", { Accept: "application/json" }, { steps: 10 });

		expect(response).toEqual({ status: 201, data: { id: 7 } });
		expect(nativeHttp.request).toHaveBeenCalledWith({ method: "POST", url: "https://api.test/api/me/steps", headers: { Accept: "application/json" }, data: { steps: 10 } });
		expect(fetchMock).not.toHaveBeenCalled();
	});

	it("goes through fetch in a browser, with a JSON body and no body for a GET", async () => {
		platform.native = false;
		fetchMock.mockResolvedValue({ status: 200, text: async () => "{\"quests\":[]}" });

		expect(await httpRequest("PUT", "https://api.test/api/me/privacy", {}, { share_steps: true })).toEqual({ status: 200, data: { quests: [] } });
		expect(fetchMock.mock.calls[0][1]).toEqual({ method: "PUT", headers: {}, body: "{\"share_steps\":true}" });

		await httpRequest("GET", "https://api.test/api/me", {});
		expect(fetchMock.mock.calls[1][1].body).toBeUndefined();
		expect(nativeHttp.request).not.toHaveBeenCalled();
	});
});
