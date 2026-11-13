import { beforeEach, describe, expect, it, vi } from "vitest";
import { isCancellation, QrScanError, scanQrCode } from "~/utils/qrScanner";

const { scanBarcode, isNativePlatform } = vi.hoisted(() => ({ scanBarcode: vi.fn(), isNativePlatform: vi.fn() }));

vi.mock("@capacitor/barcode-scanner", () => ({
	CapacitorBarcodeScanner: { scanBarcode },
	CapacitorBarcodeScannerCameraDirection: { BACK: 1, FRONT: 2 },
	CapacitorBarcodeScannerTypeHint: { QR_CODE: 0 }
}));

vi.mock("@capacitor/core", async (importOriginal) => ({
	...(await importOriginal<typeof import("@capacitor/core")>()),
	Capacitor: { isNativePlatform }
}));

function stubWebcam(getUserMedia: () => Promise<unknown>): void {
	vi.stubGlobal("navigator", { ...navigator, mediaDevices: { getUserMedia } });
}

function domError(name: string): Error {
	const error = new Error(name);
	error.name = name;

	return error;
}

describe("scanQrCode", () => {
	beforeEach(() => {
		scanBarcode.mockReset();
		isNativePlatform.mockReset().mockReturnValue(true);
		vi.unstubAllGlobals();
	});

	it("opens the back camera for a QR code and returns the trimmed text", async () => {
		scanBarcode.mockResolvedValue({ ScanResult: "  eyJpdiI6...  ", format: 0 });

		await expect(scanQrCode()).resolves.toBe("eyJpdiI6...");
		expect(scanBarcode).toHaveBeenCalledWith(expect.objectContaining({ hint: 0, cameraDirection: 1 }));
	});

	it("returns null when nothing was read", async () => {
		scanBarcode.mockResolvedValue({ ScanResult: "   ", format: 0 });

		await expect(scanQrCode()).resolves.toBeNull();
	});

	it("returns null when the player closes the scanner", async () => {
		scanBarcode.mockRejectedValue(new Error("Couldn’t scan because the process was cancelled."));

		await expect(scanQrCode()).resolves.toBeNull();
	});

	it("turns any other failure into a readable French error", async () => {
		scanBarcode.mockRejectedValue(new Error("Camera permission denied"));

		const failure = scanQrCode();

		await expect(failure).rejects.toBeInstanceOf(QrScanError);
		await expect(failure).rejects.toThrow(/caméra/);
	});

	it("in the browser, says so instead of hanging when there is no webcam", async () => {
		isNativePlatform.mockReturnValue(false);
		stubWebcam(() => Promise.reject(domError("NotFoundError")));

		await expect(scanQrCode()).rejects.toThrow(/Aucune caméra/);
		expect(scanBarcode).not.toHaveBeenCalled();
	});

	it("in the browser, reports a refused camera permission", async () => {
		isNativePlatform.mockReturnValue(false);
		stubWebcam(() => Promise.reject(domError("NotAllowedError")));

		await expect(scanQrCode()).rejects.toThrow(/le droit d'y accéder/);
		expect(scanBarcode).not.toHaveBeenCalled();
	});

	it("in the browser, releases the test stream then opens the scanner", async () => {
		isNativePlatform.mockReturnValue(false);
		const stop = vi.fn();
		stubWebcam(() => Promise.resolve({ getTracks: () => [{ stop }] }));
		scanBarcode.mockResolvedValue({ ScanResult: "jeton", format: 0 });

		await expect(scanQrCode()).resolves.toBe("jeton");
		expect(stop).toHaveBeenCalled();
	});

	it("recognises a cancellation from its message", () => {
		expect(isCancellation(new Error("Scan cancelled by user"))).toBe(true);
		expect(isCancellation("CANCELED")).toBe(true);
		expect(isCancellation(new Error("No camera"))).toBe(false);
		expect(isCancellation(undefined)).toBe(false);
	});
});
