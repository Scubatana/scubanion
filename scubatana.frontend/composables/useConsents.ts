import { Preferences } from "@capacitor/preferences";
import { defaultConsents, parseConsents, type PrivacyConsents } from "~/utils/consents";

const CONSENTS_KEY_PREFIX = "consents.";

export function useConsents() {
	const consents = useState<PrivacyConsents>("privacy-consents", defaultConsents);
	const loadedOwner = useState<number | null>("privacy-consents-owner", () => null);

	function currentPlayerId(): number | null {
		return usePlayer().player.value?.id ?? useSession().playerId.value;
	}

	function storageKey(): string | null {
		const playerId = currentPlayerId();

		return playerId === null ? null : `${CONSENTS_KEY_PREFIX}${playerId}`;
	}

	async function ensureLoaded(): Promise<PrivacyConsents> {
		const playerId = currentPlayerId();
		const key = storageKey();

		if (key === null || loadedOwner.value === playerId) {
			return consents.value;
		}

		consents.value = parseConsents((await Preferences.get({ key })).value);
		loadedOwner.value = playerId;

		return consents.value;
	}

	async function persist(next: PrivacyConsents): Promise<void> {
		consents.value = next;
		const playerId = currentPlayerId();
		const key = storageKey();

		if (key !== null) {
			loadedOwner.value = playerId;
			await Preferences.set({ key, value: JSON.stringify(next) });
		}
	}

	async function setStepsConsent(enabled: boolean): Promise<void> {
		await ensureLoaded();
		await persist({ ...consents.value, steps: enabled });

		if (enabled) {
			await useSteps().start(true);
		} else {
			await useSteps().reset();
		}
	}

	async function setLocationConsent(enabled: boolean): Promise<void> {
		await ensureLoaded();
		await persist({ ...consents.value, location: enabled });

		if (enabled) {
			await usePosition().start(true);
		} else {
			usePosition().stop();
		}
	}

	function reset(): void {
		consents.value = defaultConsents();
		loadedOwner.value = null;
	}

	async function discard(): Promise<void> {
		const key = storageKey();
		reset();

		if (key !== null) {
			await Preferences.remove({ key });
		}
	}

	return { consents, ensureLoaded, setStepsConsent, setLocationConsent, reset, discard };
}
