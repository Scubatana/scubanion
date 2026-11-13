import { Capacitor, registerPlugin, type PluginListenerHandle } from "@capacitor/core";

const BORNE_TOKEN_PATTERN = /^[a-z0-9-]{3,16}\.[0-9a-z]{1,8}\.[A-Za-z0-9_-]{16}$/;
const BORNE_TOKEN_MAX_LENGTH = 40;

interface NfcPlugin {
	isAvailable(): Promise<{ available: boolean; enabled: boolean }>;
	takePending(): Promise<{ url: string | null }>;
	addListener(eventName: "tag", listener: (event: { url: string }) => void): Promise<PluginListenerHandle>;
}

const Nfc = registerPlugin<NfcPlugin>("Nfc");

export function isValidBorneToken(token: string): boolean {
	return token.length > 0 && token.length <= BORNE_TOKEN_MAX_LENGTH && BORNE_TOKEN_PATTERN.test(token);
}

/**
 * Extracts a signed terminal passage token from a borne NDEF URL or raw token string.
 */
export function extractBorneToken(rawInput: string): string | null {
	const trimmed = rawInput.trim();

	if (trimmed === "") {
		return null;
	}

	if (isValidBorneToken(trimmed)) {
		return trimmed;
	}

	try {
		const url = new URL(trimmed);

		if (url.protocol !== "https:" && url.protocol !== "http:") {
			return null;
		}

		const match = /^\/b\/([^/?#]+)\/?$/.exec(url.pathname);

		if (!match?.[1]) {
			return null;
		}

		const decoded = decodeURIComponent(match[1]);

		return isValidBorneToken(decoded) ? decoded : null;
	} catch {
		return null;
	}
}

export async function nfcStatus(): Promise<{ available: boolean; enabled: boolean }> {
	if (!Capacitor.isNativePlatform()) {
		return { available: false, enabled: false };
	}

	return Nfc.isAvailable();
}

export async function watchNfcTags(onUrl: (url: string) => void): Promise<() => Promise<void>> {
	if (!Capacitor.isNativePlatform()) {
		return async () => undefined;
	}

	const handle = await Nfc.addListener("tag", (event) => {
		if (event?.url) {
			onUrl(event.url);
		}
	});

	const initial = await Nfc.takePending();

	if (initial?.url) {
		onUrl(initial.url);
	}

	return async () => {
		await handle.remove();
	};
}
