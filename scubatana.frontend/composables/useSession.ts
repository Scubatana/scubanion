import { Preferences } from "@capacitor/preferences";

const TOKEN_KEY = "session.token";

const PROFILE_KEY = "session.profileCompleted";

const PLAYER_KEY = "session.playerId";

export function useSession() {
	const token = useState<string | null>("session-token", () => null);
	const profileCompleted = useState<boolean>("session-profile-completed", () => false);
	const playerId = useState<number | null>("session-player-id", () => null);
	const restored = useState<boolean>("session-restored", () => false);

	async function restore(): Promise<void> {
		if (restored.value) {
			return;
		}

		token.value = (await Preferences.get({ key: TOKEN_KEY })).value;
		profileCompleted.value = (await Preferences.get({ key: PROFILE_KEY })).value === "true";
		const storedPlayer = Number((await Preferences.get({ key: PLAYER_KEY })).value);
		playerId.value = Number.isInteger(storedPlayer) && storedPlayer > 0 ? storedPlayer : null;
		restored.value = true;
	}

	async function open(newToken: string, completed: boolean, player: number | null = null): Promise<void> {
		token.value = newToken;
		profileCompleted.value = completed;
		restored.value = true;
		await Preferences.set({ key: TOKEN_KEY, value: newToken });
		await Preferences.set({ key: PROFILE_KEY, value: String(completed) });
		await rememberPlayer(player);
	}

	async function markProfileCompleted(completed: boolean): Promise<void> {
		profileCompleted.value = completed;
		await Preferences.set({ key: PROFILE_KEY, value: String(completed) });
	}

	async function rememberPlayer(player: number | null): Promise<void> {
		if (player === playerId.value) {
			return;
		}

		playerId.value = player;

		if (player === null) {
			await Preferences.remove({ key: PLAYER_KEY });
		} else {
			await Preferences.set({ key: PLAYER_KEY, value: String(player) });
		}
	}

	async function clear(): Promise<void> {
		token.value = null;
		profileCompleted.value = false;
		playerId.value = null;
		await Preferences.remove({ key: TOKEN_KEY });
		await Preferences.remove({ key: PROFILE_KEY });
		await Preferences.remove({ key: PLAYER_KEY });
	}

	return { token, profileCompleted, playerId, restore, open, markProfileCompleted, rememberPlayer, clear };
}
