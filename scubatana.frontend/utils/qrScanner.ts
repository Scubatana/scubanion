import { CapacitorBarcodeScanner, CapacitorBarcodeScannerCameraDirection, CapacitorBarcodeScannerTypeHint } from "@capacitor/barcode-scanner";

export class QrScanError extends Error {}

/**
 * Ouvre la caméra et lit un QR code. Écran natif sur Android, webcam dans le navigateur.
 * Renvoie le texte lu, ou null si le joueur a fermé le scanner sans rien scanner.
 */
export async function scanQrCode(): Promise<string | null> {
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

		throw new QrScanError("Impossible d'utiliser la caméra. Vérifie que Scubanion a le droit d'y accéder, ou saisis le code de ton ami.");
	}
}

export function isCancellation(error: unknown): boolean {
	const message = error instanceof Error ? error.message : String(error ?? "");

	return /cancel/i.test(message);
}
