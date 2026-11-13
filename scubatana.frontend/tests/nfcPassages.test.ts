import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { extractBorneToken, isValidBorneToken } from "~/utils/nfc";
import { PASSAGE_MAX_AGE_MS, parsePassageQueue, pruneExpiredPassages } from "~/utils/passageQueue";

const { httpRequest, navigateTo, storage, currentPosition, nfcState } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>(),
	currentPosition: vi.fn(),
	nfcState: {
		pendingUrl: null as string | null,
		listener: null as ((event: { url: string }) => void) | null
	}
}));

vi.mock("~/utils/http", () => ({ httpRequest }));

vi.mock("~/utils/geolocation", () => ({ currentPosition }));

vi.mock("@capacitor/core", () => ({
	Capacitor: {
		isNativePlatform: () => true
	},
	registerPlugin: (name: string) => {
		if (name === "Nfc") {
			return {
				isAvailable: async () => ({ available: true, enabled: true }),
				takePending: async () => {
					const url = nfcState.pendingUrl;
					nfcState.pendingUrl = null;
					return { url };
				},
				addListener: async (_event: string, listener: (event: { url: string }) => void) => {
					nfcState.listener = listener;
					return {
						remove: async () => {
							nfcState.listener = null;
						}
					};
				}
			};
		}

		return {
			checkPermissions: async () => ({ activity: "granted", location: "granted" }),
			requestPermissions: async () => ({ activity: "granted", location: "granted" }),
			addListener: async () => ({ remove: async () => undefined })
		};
	}
}));

vi.mock("@capacitor/app", () => ({
	App: {
		addListener: async () => ({ remove: async () => undefined })
	}
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

const sampleToken = "chateau-01.t3k2w0.AbCdEfGhIjKlMnOp";
const sampleUrl = `https://scuba.picsou06.fr/b/${sampleToken}`;

describe("nfc token and queue utils", () => {
	it("extracts valid borne tokens from HTTPS URLs or raw tokens and rejects malformed ones", () => {
		expect(isValidBorneToken(sampleToken)).toBe(true);
		expect(extractBorneToken(sampleUrl)).toBe(sampleToken);
		expect(extractBorneToken(`  ${sampleUrl}/  `)).toBe(sampleToken);
		expect(extractBorneToken(sampleToken)).toBe(sampleToken);
		expect(extractBorneToken("https://example.com/autre/chemin")).toBeNull();
		expect(extractBorneToken("https://scuba.picsou06.fr/b/trop-court")).toBeNull();
		expect(extractBorneToken("ftp://scuba.picsou06.fr/b/" + sampleToken)).toBeNull();
	});

	it("prunes offline passages older than 24 hours", () => {
		const now = Date.parse("2026-10-14T15:00:00.000Z");
		const fresh = { token: sampleToken, recorded_at: new Date(now - 60_000).toISOString() };
		const expired = { token: "chateau-01.t3j000.AbCdEfGhIjKlMnOp", recorded_at: new Date(now - PASSAGE_MAX_AGE_MS - 1_000).toISOString() };

		expect(parsePassageQueue("invalide")).toEqual([]);
		expect(pruneExpiredPassages([fresh, expired], now)).toEqual([fresh]);
	});
});

describe("usePassages", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		currentPosition.mockReset();
		storage.clear();
		nfcState.pendingUrl = null;
		nfcState.listener = null;
		clearNuxtState();

		await usePassages().stopListening();
		await useSession().open("jeton-1", true, 7);
		usePlayer().player.value = {
			id: 7,
			email: "lea@example.com",
			pseudo: "Lea",
			birth_date: "2004-05-12",
			gender: null,
			ambition: "fitness",
			profile_completed: true,
			stats: { total_steps: 1000, quests_done: 0, friends_count: 0 }
		};
	});

	it("submits scanned borne NFC URL with location when consented and updates companion", async () => {
		storage.set("consents.7", JSON.stringify({ steps: true, location: true }));
		currentPosition.mockResolvedValueOnce({
			latitude: 43.6953,
			longitude: 7.2806,
			accuracy_m: 8,
			mocked: false,
			recorded_at: "2026-10-13T15:00:00.000Z"
		});
		const updatedCompanion = {
			name: "Bulle",
			species: "dog",
			color: "ocean",
			accessories: [],
			level: 2,
			xp: 100,
			xp_for_next_level: 1000,
			mood: 100,
			health: 100
		};
		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: {
				scanned_at: "2026-10-13T15:00:00+02:00",
				status: "rewarded",
				message: "Bravo Lea ! +100 XP",
				reward_xp: 100,
				terminal: { code: "chateau-01", name: "Borne du Château" },
				companion: updatedCompanion
			}
		});

		const outcome = await usePassages().scan(sampleUrl);

		expect(outcome).toBe("submitted");
		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/me\/passages$/);
		expect(httpRequest.mock.calls[0][3]).toEqual({
			token: sampleToken,
			recorded_at: "2026-10-13T15:00:00.000Z",
			latitude: 43.6953,
			longitude: 7.2806,
			accuracy_m: 8,
			mocked: false
		});
		expect(usePassages().lastNotice.value).toBe("Bravo Lea ! +100 XP");
		expect(usePlayer().companion.value).toEqual(updatedCompanion);
	});

	it("does not read GPS when location consent is false and queues offline passages for later flush", async () => {
		storage.set("consents.7", JSON.stringify({ steps: true, location: false }));
		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));

		const passages = usePassages();
		const outcome = await passages.scan(sampleUrl);

		expect(currentPosition).not.toHaveBeenCalled();
		expect(outcome).toBe("queued");
		expect(passages.pending.value).toHaveLength(1);
		expect(passages.lastNotice.value).toContain("hors ligne");

		httpRequest.mockResolvedValueOnce({
			status: 200,
			data: {
				scanned_at: "2026-10-13T15:00:00+02:00",
				status: "rewarded",
				message: "Bravo Lea ! +100 XP",
				reward_xp: 100,
				terminal: { code: "chateau-01", name: "Borne du Château" },
				companion: null
			}
		});

		await passages.flush();

		expect(passages.pending.value).toEqual([]);
		expect(passages.lastNotice.value).toBe("Bravo Lea ! +100 XP");
	});

	it("keeps a passage in the queue when the server is down or overloaded, and only drops it on a real refusal", async () => {
		storage.set("consents.7", JSON.stringify({ steps: true, location: false }));
		httpRequest.mockResolvedValueOnce({ status: 503, data: {} });

		const passages = usePassages();

		expect(await passages.scan(sampleUrl)).toBe("queued");
		expect(passages.lastNotice.value).toContain("Serveur indisponible");

		httpRequest.mockResolvedValueOnce({ status: 429, data: {} });
		await passages.flush();

		expect(passages.pending.value).toHaveLength(1);
		expect(passages.lastError.value).toBe("");

		httpRequest.mockResolvedValueOnce({ status: 500, data: {} });
		await passages.flush();

		expect(JSON.parse(storage.get("passages.pending.7") ?? "[]")).toHaveLength(1);

		httpRequest.mockResolvedValueOnce({ status: 409, data: { message: "Ce jeton a déjà été utilisé." } });
		await passages.flush();

		expect(passages.pending.value).toEqual([]);
		expect(passages.lastError.value).toBe("Ce jeton a déjà été utilisé.");
	});

	it("consumes cold-start NFC intent and listens for live native NFC tag events", async () => {
		nfcState.pendingUrl = sampleUrl;
		httpRequest.mockResolvedValue({
			status: 200,
			data: {
				scanned_at: "2026-10-13T15:00:00+02:00",
				status: "already_rewarded",
				message: "Déjà passé aujourd'hui, Lea.",
				reward_xp: 0,
				terminal: { code: "chateau-01", name: "Borne du Château" },
				companion: null
			}
		});

		await usePassages().startListening();
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(httpRequest).toHaveBeenCalledTimes(1);
		expect(usePassages().lastNotice.value).toBe("Déjà passé aujourd'hui, Lea.");

		nfcState.listener?.({ url: sampleUrl });
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(httpRequest).toHaveBeenCalledTimes(2);
	});

	it("queues a cold-start offline scan using the restored session player id before /me finishes loading", async () => {
		usePlayer().player.value = null;
		httpRequest.mockRejectedValueOnce(new TypeError("Failed to fetch"));

		const outcome = await usePassages().scan(sampleUrl);

		expect(outcome).toBe("queued");
		expect(JSON.parse(storage.get("passages.pending.7") ?? "[]")).toHaveLength(1);
	});
});
