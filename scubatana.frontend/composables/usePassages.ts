import { App } from "@capacitor/app";
import { Preferences } from "@capacitor/preferences";
import { ApiError, isTransientError, readableMessage } from "~/utils/apiError";
import { currentPosition } from "~/utils/geolocation";
import { extractBorneToken, watchNfcTags } from "~/utils/nfc";
import {
	parsePassageQueue,
	pruneExpiredPassages,
	withoutPassage,
	withPassage,
	type PendingPassage
} from "~/utils/passageQueue";
import type { Companion } from "~/utils/types";

export interface PassageTerminalInfo {
	code: string;
	name: string;
}

export interface PassageResponse {
	scanned_at: string;
	status: "rewarded" | "already_rewarded";
	message: string;
	reward_xp: number;
	terminal: PassageTerminalInfo;
	companion: Companion | null;
}

const PASSAGES_KEY_PREFIX = "passages.pending.";

let flushing = false;
let listenersAttached = false;
let stopNfcWatch: (() => Promise<void>) | null = null;
let initialized = false;

export function usePassages() {
	const pending = useState<PendingPassage[]>("passages-pending", () => []);
	const pendingOwner = useState<number | null>("passages-pending-owner", () => null);
	const submitting = useState<boolean>("passages-submitting", () => false);
	const lastResult = useState<PassageResponse | null>("passages-last-result", () => null);
	const lastNotice = useState<string>("passages-last-notice", () => "");
	const lastError = useState<string>("passages-last-error", () => "");
	const deferredUrl = useState<string | null>("passages-deferred-url", () => null);

	const session = useSession();
	const player = usePlayer();

	if (!initialized) {
		initialized = true;
		watch([session.token, player.player], ([token, currentPlayer]) => {
			if (token === null) {
				reset();
			} else if (deferredUrl.value && currentPlayer) {
				const pendingTag = deferredUrl.value;
				deferredUrl.value = null;
				void scan(pendingTag);
			}
		});
	}

	function activePlayerId(): number | null {
		return player.player.value?.id ?? session.playerId.value;
	}

	function queueKey(): string | null {
		const id = activePlayerId();

		return id === null ? null : `${PASSAGES_KEY_PREFIX}${id}`;
	}

	async function restorePending(): Promise<void> {
		const playerId = activePlayerId();
		const key = queueKey();

		if (key === null || pendingOwner.value === playerId) {
			return;
		}

		const stored = parsePassageQueue((await Preferences.get({ key })).value);
		const valid = pruneExpiredPassages(stored);
		pending.value = valid;
		pendingOwner.value = playerId;

		if (valid.length !== stored.length) {
			await Preferences.set({ key, value: JSON.stringify(valid) });
		}
	}

	async function savePending(queue: PendingPassage[]): Promise<void> {
		pending.value = queue;
		const key = queueKey();

		if (key !== null) {
			await Preferences.set({ key, value: JSON.stringify(queue) });
		}
	}

	async function buildEntry(token: string): Promise<PendingPassage> {
		const recordedAt = new Date().toISOString();
		const consents = await useConsents().ensureLoaded();

		if (!consents.location) {
			return { token, recorded_at: recordedAt };
		}

		try {
			const fix = await currentPosition(false);

			return {
				token,
				recorded_at: fix.recorded_at,
				latitude: fix.latitude,
				longitude: fix.longitude,
				accuracy_m: fix.accuracy_m,
				mocked: fix.mocked
			};
		} catch {
			return { token, recorded_at: recordedAt };
		}
	}

	async function postPassage(entry: PendingPassage): Promise<PassageResponse> {
		const response = await useApi().post<PassageResponse>("/me/passages", entry);

		if (response.companion !== null) {
			player.companion.value = response.companion;
		}

		lastResult.value = response;
		lastNotice.value = response.message;
		lastError.value = "";

		return response;
	}

	async function scan(rawInput: string): Promise<"submitted" | "queued" | null> {
		const token = extractBorneToken(rawInput);

		if (token === null) {
			lastError.value = "Ce repère NFC ne correspond pas à une borne Scubanion.";
			return null;
		}

		if (session.token.value === null) {
			deferredUrl.value = rawInput;
			lastError.value = "Connecte-toi pour enregistrer ton passage à la borne.";
			return null;
		}

		submitting.value = true;
		lastError.value = "";
		lastNotice.value = "";

		try {
			const entry = await buildEntry(token);

			try {
				await postPassage(entry);
				return "submitted";
			} catch (error) {
				if (isTransientError(error)) {
					await restorePending();
					await savePending(withPassage(pending.value, entry));
					lastNotice.value = error instanceof ApiError && error.status === 0
						? "Pas de réseau : passage conservé hors ligne (valable 24 h)."
						: "Serveur indisponible : passage conservé, il sera renvoyé tout seul (valable 24 h).";
					return "queued";
				}

				if (error instanceof ApiError && error.status === 401) {
					reset();
					return null;
				}

				lastError.value = readablePassageError(error);
				return null;
			}
		} finally {
			submitting.value = false;
		}
	}

	async function flush(): Promise<void> {
		await restorePending();

		if (flushing || pending.value.length === 0 || session.token.value === null) {
			return;
		}

		const fresh = pruneExpiredPassages(pending.value);

		if (fresh.length !== pending.value.length) {
			await savePending(fresh);
		}

		if (fresh.length === 0) {
			return;
		}

		flushing = true;

		try {
			for (const entry of [...fresh]) {
				try {
					await postPassage(entry);
				} catch (error) {
					if (isTransientError(error)) {
						return;
					}

					if (error instanceof ApiError && error.status === 401) {
						reset();
						return;
					}

					lastError.value = readablePassageError(error);
				}

				await savePending(withoutPassage(pending.value, entry.token));
			}
		} finally {
			flushing = false;
		}
	}

	async function startListening(): Promise<void> {
		await session.restore();

		if (listenersAttached) {
			await flush().catch(() => undefined);
			return;
		}

		listenersAttached = true;

		await App.addListener("resume", () => {
			if (session.token.value !== null) {
				void flush().catch(() => undefined);
			}
		});

		stopNfcWatch = await watchNfcTags((url) => {
			void scan(url);
		});

		await flush().catch(() => undefined);
	}

	function dismissFeedback(): void {
		lastResult.value = null;
		lastNotice.value = "";
		lastError.value = "";
	}

	function reset(): void {
		pending.value = [];
		pendingOwner.value = null;
		submitting.value = false;
		dismissFeedback();
	}

	async function discard(): Promise<void> {
		const key = queueKey();
		reset();

		if (key !== null) {
			await Preferences.remove({ key });
		}
	}

	async function stopListening(): Promise<void> {
		await stopNfcWatch?.().catch(() => undefined);
		stopNfcWatch = null;
		listenersAttached = false;
	}

	return {
		pending,
		submitting,
		lastResult,
		lastNotice,
		lastError,
		scan,
		flush,
		startListening,
		stopListening,
		dismissFeedback,
		reset,
		discard
	};
}

function readablePassageError(error: unknown): string {
	if (error instanceof ApiError) {
		return error.fields.token ?? error.fields.position ?? error.message;
	}

	return readableMessage(error);
}
