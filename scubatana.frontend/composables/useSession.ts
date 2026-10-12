import { Preferences } from "@capacitor/preferences";

const TOKEN_KEY = "session.token";

const PROFILE_KEY = "session.profileCompleted";

export function useSession() {
	const token = useState<string | null>("session-token", () => null);
	const profileCompleted = useState<boolean>("session-profile-completed", () => false);
	const restored = useState<boolean>("session-restored", () => false);

	async function restore(): Promise<void> {
		if (restored.value) {
			return;
		}

		token.value = (await Preferences.get({ key: TOKEN_KEY })).value;
		profileCompleted.value = (await Preferences.get({ key: PROFILE_KEY })).value === "true";
		restored.value = true;
	}

	async function open(newToken: string, completed: boolean): Promise<void> {
		token.value = newToken;
		profileCompleted.value = completed;
		restored.value = true;
		await Preferences.set({ key: TOKEN_KEY, value: newToken });
		await Preferences.set({ key: PROFILE_KEY, value: String(completed) });
	}

	async function markProfileCompleted(completed: boolean): Promise<void> {
		profileCompleted.value = completed;
		await Preferences.set({ key: PROFILE_KEY, value: String(completed) });
	}

	async function clear(): Promise<void> {
		token.value = null;
		profileCompleted.value = false;
		await Preferences.remove({ key: TOKEN_KEY });
		await Preferences.remove({ key: PROFILE_KEY });
	}

	return { token, profileCompleted, restore, open, markProfileCompleted, clear };
}
