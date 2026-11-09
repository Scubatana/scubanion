import type { BlockedPlayer, BlocksView, ReportReason } from "~/utils/safety";

/**
 * Sécurité entre joueurs : bloquer, débloquer et signaler (docs/api/securite.md).
 * Bloquer supprime aussi l'amitié côté serveur : la page doit recharger la liste d'amis ensuite.
 */
export function useSafety() {
	const blocked = useState<BlockedPlayer[]>("safety-blocked", () => []);
	const loaded = useState<boolean>("safety-loaded", () => false);

	const session = useSession();
	const player = usePlayer();

	watch([session.token, player.player], ([token, currentPlayer]) => {
		if (token === null || currentPlayer === null) {
			reset();
		}
	}, { immediate: true });

	function applyView(view: BlocksView): void {
		blocked.value = view.blocked;
		loaded.value = true;
	}

	async function load(): Promise<void> {
		applyView(await useApi().get<BlocksView>("/me/blocks"));
	}

	async function block(playerId: number): Promise<void> {
		applyView(await useApi().post<BlocksView>("/me/blocks", { player_id: playerId }));
	}

	async function unblock(id: number): Promise<void> {
		applyView(await useApi().del<BlocksView>(`/me/blocks/${id}`));
	}

	async function report(playerId: number, reason: ReportReason, comment: string): Promise<string> {
		const trimmed = comment.trim();
		const response = await useApi().post<{ message: string }>("/me/reports", {
			player_id: playerId,
			reason,
			...(trimmed === "" ? {} : { comment: trimmed })
		});

		return response.message;
	}

	function reset(): void {
		blocked.value = [];
		loaded.value = false;
	}

	return { blocked, loaded, load, block, unblock, report, reset };
}
