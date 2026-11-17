import { encounterMessage, type EncounterResult } from "~/utils/encounters";
import { currentPosition, type CurrentPosition } from "~/utils/geolocation";
import { scanQrCode } from "~/utils/qrScanner";

export function useEncounters() {
	async function meet(token: string, position: CurrentPosition | null): Promise<EncounterResult> {
		const result = await useApi().post<EncounterResult>("/me/encounters", { token: token.trim(), ...(position ?? {}) });

		if (result.new) {
			await useQuests().load().catch(() => undefined);
		}

		return result;
	}

	// Scanne le QR de l'autre joueur et valide la rencontre. Sans position (localisation refusée ou
	// introuvable), le serveur se rabat sur la dernière position reçue, si elle a moins de 10 minutes.
	async function scanAndMeet(): Promise<string | null> {
		const token = await scanQrCode("Vise le QR code de l'autre joueur");

		if (token === null) {
			return null;
		}

		const position = await currentPosition(true).catch(() => null);

		return encounterMessage(await meet(token, position));
	}

	return { meet, scanAndMeet };
}
