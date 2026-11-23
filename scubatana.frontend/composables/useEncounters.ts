import { encounterMessage, type EncounterResult } from "~/utils/encounters";
import type { FriendQrToken } from "~/utils/friends";
import { currentPosition, type CurrentPosition } from "~/utils/geolocation";
import { scanQrCode } from "~/utils/qrScanner";

export const MEET_NEEDS_LOCATION = "Active la position géographique dans les Paramètres pour valider une rencontre : vos positions doivent se recouper.";

export function useEncounters() {
	// QR de rencontre, distinct du QR d'ami : celui qui le scanne ne devient pas ami pour autant.
	async function qrToken(): Promise<FriendQrToken> {
		return useApi().get<FriendQrToken>("/me/encounters/qr");
	}

	async function meet(token: string, position: CurrentPosition | null): Promise<EncounterResult> {
		const result = await useApi().post<EncounterResult>("/me/encounters", { token: token.trim(), ...(position ?? {}) });

		if (result.new) {
			await useQuests().load().catch(() => undefined);
		}

		return result;
	}

	// Scanne le QR de l'autre joueur et valide la rencontre. La position n'est lue qu'avec le consentement
	// « position géographique » des Paramètres ; sans lui, une rencontre par QR est impossible.
	// Position introuvable : le serveur se rabat sur la dernière reçue, si elle a moins de 10 minutes.
	async function scanAndMeet(): Promise<string | null> {
		if (!(await useConsents().ensureLoaded()).location) {
			throw new Error(MEET_NEEDS_LOCATION);
		}

		const token = await scanQrCode("Vise le QR code de l'autre joueur");

		if (token === null) {
			return null;
		}

		const position = await currentPosition(true).catch(() => null);

		return encounterMessage(await meet(token, position));
	}

	return { qrToken, meet, scanAndMeet };
}
