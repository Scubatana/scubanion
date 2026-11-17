import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "~/utils/apiError";
import { encounterMessage } from "~/utils/encounters";

const { httpRequest, scanQrCode, currentPosition, storage } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	scanQrCode: vi.fn(),
	currentPosition: vi.fn(),
	storage: new Map<string, string>()
}));

vi.mock("~/utils/http", () => ({ httpRequest }));
vi.mock("~/utils/qrScanner", () => ({ scanQrCode }));
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

const here = { latitude: 43.6953, longitude: 7.2806, accuracy_m: 12, mocked: false, recorded_at: "2026-11-17T15:00:00.000Z" };

function requests(): Array<[string, string, unknown]> {
	return httpRequest.mock.calls.map(([method, url, , body]) => [method, String(url).replace(/^.*\/api/, ""), body]);
}

describe("encounterMessage", () => {
	it("celebrates a new encounter and explains an encounter already counted today", () => {
		expect(encounterMessage({ player: { pseudo: "Lea" }, met_at: "2026-11-17T16:00:00+01:00", new: true })).toBe("Rencontre avec Lea validée !");
		expect(encounterMessage({ player: { pseudo: "Lea" }, met_at: "2026-11-17T16:00:00+01:00", new: false })).toBe("Ta rencontre avec Lea est déjà comptée aujourd'hui.");
	});
});

describe("useEncounters", () => {
	beforeEach(async () => {
		httpRequest.mockReset();
		scanQrCode.mockReset();
		currentPosition.mockReset();
		storage.clear();
		clearNuxtState();
		await useSession().open("jeton-1", true);
	});

	it("does nothing when the scanner is closed without reading a QR", async () => {
		scanQrCode.mockResolvedValue(null);

		await expect(useEncounters().scanAndMeet()).resolves.toBeNull();
		expect(scanQrCode).toHaveBeenCalledWith("Vise le QR code de l'autre joueur");
		expect(httpRequest).not.toHaveBeenCalled();
	});

	it("sends the scanned QR with the current position, then reloads the quests", async () => {
		scanQrCode.mockResolvedValue("  eyJpdiI6...  ");
		currentPosition.mockResolvedValue(here);
		httpRequest
			.mockResolvedValueOnce({ status: 201, data: { player: { pseudo: "Lea" }, met_at: "2026-11-17T16:00:00+01:00", new: true } })
			.mockResolvedValueOnce({ status: 200, data: { quests: [] } });

		await expect(useEncounters().scanAndMeet()).resolves.toBe("Rencontre avec Lea validée !");

		expect(currentPosition).toHaveBeenCalledWith(true);
		expect(requests()[0]).toEqual(["POST", "/me/encounters", { token: "eyJpdiI6...", ...here }]);
		expect(requests().map(([method, path]) => `${method} ${path}`)).toContain("GET /me/quests");
	});

	it("sends the QR alone when the position cannot be read, and leaves the quests alone for a repeat", async () => {
		scanQrCode.mockResolvedValue("eyJpdiI6...");
		currentPosition.mockRejectedValue(new Error("Active la localisation pour valider ce lieu."));
		httpRequest.mockResolvedValueOnce({ status: 200, data: { player: { pseudo: "Lea" }, met_at: "2026-11-17T16:00:00+01:00", new: false } });

		await expect(useEncounters().scanAndMeet()).resolves.toBe("Ta rencontre avec Lea est déjà comptée aujourd'hui.");

		expect(requests()).toEqual([["POST", "/me/encounters", { token: "eyJpdiI6..." }]]);
	});

	it("passes on the reason given by the server when the encounter is refused", async () => {
		scanQrCode.mockResolvedValue("eyJpdiI6...");
		currentPosition.mockResolvedValue(here);
		httpRequest.mockResolvedValueOnce({
			status: 422,
			data: { message: "Erreur", errors: { position: ["Vous semblez trop loin l'un de l'autre : rapprochez-vous, puis scanne à nouveau."] } }
		});

		const failure = useEncounters().scanAndMeet();

		await expect(failure).rejects.toBeInstanceOf(ApiError);
		await expect(failure).rejects.toMatchObject({ fields: { position: "Vous semblez trop loin l'un de l'autre : rapprochez-vous, puis scanne à nouveau." } });
	});
});
