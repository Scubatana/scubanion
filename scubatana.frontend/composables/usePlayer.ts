import type { Companion, CompanionLookPayload, Player, ProfilePayload, Today } from "~/utils/types";

interface MeResponse {
	player: Player;
	companion: Companion | null;
	today: Today;
}

interface CompanionResponse {
	companion: Companion;
}

interface ProfileResponse {
	player: Player;
	companion: Companion;
}

export function usePlayer() {
	const player = useState<Player | null>("player", () => null);
	const companion = useState<Companion | null>("player-companion", () => null);
	const today = useState<Today | null>("player-today", () => null);

	async function load(): Promise<void> {
		const response = await useApi().get<MeResponse>("/me");

		player.value = response.player;
		companion.value = response.companion;
		today.value = response.today;
		await useSession().markProfileCompleted(response.player.profile_completed);
	}

	async function saveProfile(payload: ProfilePayload): Promise<void> {
		const response = await useApi().put<ProfileResponse>("/me/profile", payload);

		player.value = response.player;
		companion.value = response.companion;
		await useSession().markProfileCompleted(response.player.profile_completed);
	}

	async function saveCompanion(payload: CompanionLookPayload): Promise<void> {
		const response = await useApi().put<CompanionResponse>("/me/companion", payload);

		companion.value = response.companion;
	}

	function reset(): void {
		player.value = null;
		companion.value = null;
		today.value = null;
	}

	return { player, companion, today, load, saveProfile, saveCompanion, reset };
}
