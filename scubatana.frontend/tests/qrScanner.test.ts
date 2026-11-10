import { beforeEach, describe, expect, it, vi } from "vitest";
import { isCancellation, QrScanError, scanQrCode } from "~/utils/qrScanner";

const { scanBarcode } = vi.hoisted(() => ({ scanBarcode: vi.fn() }));

vi.mock("@capacitor/barcode-scanner", () => ({
	CapacitorBarcodeScanner: { scanBarcode },
	CapacitorBarcodeScannerCameraDirection: { BACK: 1, FRONT: 2 },
	CapacitorBarcodeScannerTypeHint: { QR_CODE: 0 }
}));

describe("scanQrCode", () => {
	beforeEach(() => {
		scanBarcode.mockReset();
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

	it("recognises a cancellation from its message", () => {
		expect(isCancellation(new Error("Scan cancelled by user"))).toBe(true);
		expect(isCancellation("CANCELED")).toBe(true);
		expect(isCancellation(new Error("No camera"))).toBe(false);
		expect(isCancellation(undefined)).toBe(false);
	});
});
