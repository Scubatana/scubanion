import type { Player } from "~/utils/types";

interface VerifyResponse {
	token: string;
	player: Player;
}

export function useAuth() {
	const api = useApi();
	const session = useSession();
	const player = usePlayer();
	const pendingEmail = useState<string | null>("auth-pending-email", () => null);

	async function requestCode(email: string): Promise<void> {
		const normalized = email.trim().toLowerCase();

		await api.post<void>("/auth/code", { email: normalized });
		pendingEmail.value = normalized;
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
		pendingEmail.value = null;
	}

	async function logout(): Promise<void> {
		await api.post<void>("/auth/logout").catch(() => undefined);
		await session.clear();
		player.reset();
	}

	return { pendingEmail, requestCode, verifyCode, logout };
}
