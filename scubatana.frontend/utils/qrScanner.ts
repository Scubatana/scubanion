import { CapacitorBarcodeScanner, CapacitorBarcodeScannerCameraDirection, CapacitorBarcodeScannerTypeHint } from "@capacitor/barcode-scanner";
import { Capacitor } from "@capacitor/core";

export class QrScanError extends Error {}

const CAMERA_ERROR = "Impossible d'utiliser la caméra. Vérifie que Scubanion a le droit d'y accéder, ou saisis le code de ton ami.";
const NO_CAMERA_ERROR = "Aucune caméra trouvée sur cet appareil. Saisis plutôt le code de ton ami.";

export async function scanQrCode(): Promise<string | null> {
	if (!Capacitor.isNativePlatform()) {
		await ensureWebCamera();
	}

	try {
		const result = await CapacitorBarcodeScanner.scanBarcode({
			hint: CapacitorBarcodeScannerTypeHint.QR_CODE,
			scanInstructions: "Vise le QR code de ton ami",
			cameraDirection: CapacitorBarcodeScannerCameraDirection.BACK
		});
		const text = result.ScanResult?.trim() ?? "";

		return text === "" ? null : text;
	} catch (error) {
		if (isCancellation(error)) {
			return null;
		}

		throw new QrScanError(CAMERA_ERROR);
	}
}

async function ensureWebCamera(): Promise<void> {
	if (typeof navigator === "undefined" || !navigator.mediaDevices?.getUserMedia) {
		throw new QrScanError(NO_CAMERA_ERROR);
	}

	try {
		const stream = await navigator.mediaDevices.getUserMedia({ video: true });
		stream.getTracks().forEach((track) => track.stop());
	} catch (error) {
		const name = error instanceof Error ? error.name : "";
		throw new QrScanError(name === "NotFoundError" || name === "OverconstrainedError" ? NO_CAMERA_ERROR : CAMERA_ERROR);
	}
}

export function isCancellation(error: unknown): boolean {
	const message = error instanceof Error ? error.message : String(error ?? "");

	return /cancel/i.test(message);
}
