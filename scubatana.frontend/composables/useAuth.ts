import { Preferences } from "@capacitor/preferences";
import { readPendingEmail, serializePendingEmail } from "~/utils/pendingEmail";
import type { Player } from "~/utils/types";

interface VerifyResponse {
	token: string;
	player: Player;
}

const PENDING_EMAIL_KEY = "auth.pendingEmail";

export function useAuth() {
	const api = useApi();
	const session = useSession();
	const player = usePlayer();
	const pendingEmail = useState<string | null>("auth-pending-email", () => null);
	const pendingRestored = useState<boolean>("auth-pending-restored", () => false);

	async function restorePendingEmail(): Promise<void> {
		if (pendingRestored.value) {
			return;
		}

		pendingEmail.value = readPendingEmail((await Preferences.get({ key: PENDING_EMAIL_KEY })).value, Date.now());
		pendingRestored.value = true;
	}

	async function forgetPendingEmail(): Promise<void> {
		pendingEmail.value = null;
		await Preferences.remove({ key: PENDING_EMAIL_KEY });
	}

	async function requestCode(email: string): Promise<void> {
		const normalized = email.trim().toLowerCase();

		await api.post<void>("/auth/code", { email: normalized });
		pendingEmail.value = normalized;
		pendingRestored.value = true;
		await Preferences.set({ key: PENDING_EMAIL_KEY, value: serializePendingEmail(normalized, Date.now()) });
	}

	async function verifyCode(code: string, termsAccepted: boolean): Promise<void> {
		const response = await api.post<VerifyResponse>("/auth/verify", {
			email: pendingEmail.value,
			code,
			terms_accepted: termsAccepted
		});

		player.reset();
		player.player.value = response.player;
		await session.open(response.token, response.player.profile_completed);
		await forgetPendingEmail();
	}

	async function logout(): Promise<void> {
		await api.post<void>("/auth/logout").catch(() => undefined);
		await useSteps().reset();
		await session.clear();
		await forgetPendingEmail();
		player.reset();
	}

	async function deleteAccount(): Promise<void> {
		await api.del<void>("/me?confirm=1");
		await useSteps().discard();
		await session.clear();
		await forgetPendingEmail();
		player.reset();
	}

	return { pendingEmail, restorePendingEmail, requestCode, verifyCode, logout, deleteAccount };
}
