import { normalizeFriendCodeInput, type CheerResult, type FriendInvitation, type FriendQrToken, type Friendship, type FriendsView } from "~/utils/friends";

export type InviteOutcome = "sent" | "mutual";

export function useFriends() {
	const code = useState<string>("friends-code", () => "");
	const friends = useState<Friendship[]>("friends-list", () => []);
	const received = useState<FriendInvitation[]>("friends-received", () => []);
	const sent = useState<FriendInvitation[]>("friends-sent", () => []);
	const loaded = useState<boolean>("friends-loaded", () => false);
	const checkedAt = useState<number>("friends-checked-at", () => 0);

	const session = useSession();
	const player = usePlayer();

	watch([session.token, player.player], ([token, currentPlayer]) => {
		if (token === null || currentPlayer === null) {
			reset();
		}
	}, { immediate: true });

	function applyView(view: FriendsView): void {
		code.value = view.code;
		friends.value = view.friends;
		received.value = view.received;
		sent.value = view.sent;
		loaded.value = true;

		if (player.player.value !== null) {
			player.player.value = {
				...player.player.value,
				stats: {
					...player.player.value.stats,
					friends_count: view.friends.length
				}
			};
		}
	}

	async function load(): Promise<void> {
		checkedAt.value = Date.now();
		await useSteps().settled();
		applyView(await useApi().get<FriendsView>("/me/friends"));
	}

	async function loadIfStale(maxAgeMs: number): Promise<void> {
		if (Date.now() - checkedAt.value < maxAgeMs) {
			return;
		}

		await load();
	}

	async function invite(rawCode: string): Promise<InviteOutcome> {
		const previousCount = friends.value.length;
		const view = await useApi().post<FriendsView>("/me/friends", {
			code: normalizeFriendCodeInput(rawCode)
		});

		applyView(view);

		return view.friends.length > previousCount ? "mutual" : "sent";
	}

	async function qrToken(): Promise<FriendQrToken> {
		return useApi().get<FriendQrToken>("/me/friends/qr");
	}

	async function scanQr(token: string): Promise<string | null> {
		const before = new Set(friends.value.map((friendship) => friendship.player.id));

		applyView(await useApi().post<FriendsView>("/me/friends/qr", { token: token.trim() }));

		return friends.value.find((friendship) => !before.has(friendship.player.id))?.player.pseudo ?? null;
	}

	async function accept(id: number): Promise<void> {
		applyView(await useApi().post<FriendsView>(`/me/friends/${id}/accept`));
	}

	async function cheer(id: number): Promise<string[]> {
		const { unlocked = [], ...view } = await useApi().post<CheerResult>(`/me/friends/${id}/cheer`);

		applyView(view);

		const companion = usePlayer().companion;

		if (unlocked.length > 0 && companion.value !== null) {
			const known = companion.value.unlocked_accessories ?? [];

			companion.value = { ...companion.value, unlocked_accessories: [...known, ...unlocked.filter((code) => !known.includes(code))] };
		}

		return unlocked;
	}

	async function remove(id: number): Promise<void> {
		applyView(await useApi().del<FriendsView>(`/me/friends/${id}`));
	}

	function reset(): void {
		code.value = "";
		friends.value = [];
		received.value = [];
		sent.value = [];
		loaded.value = false;
		checkedAt.value = 0;
	}

	return { code, friends, received, sent, loaded, load, loadIfStale, invite, qrToken, scanQr, accept, cheer, remove, reset };
}
