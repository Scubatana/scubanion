import { normalizeFriendCodeInput, type FriendInvitation, type FriendQrToken, type Friendship, type FriendsView } from "~/utils/friends";

export type InviteOutcome = "sent" | "mutual";

export function useFriends() {
	const code = useState<string>("friends-code", () => "");
	const friends = useState<Friendship[]>("friends-list", () => []);
	const received = useState<FriendInvitation[]>("friends-received", () => []);
	const sent = useState<FriendInvitation[]>("friends-sent", () => []);
	const loaded = useState<boolean>("friends-loaded", () => false);

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
		await useSteps().settled();
		applyView(await useApi().get<FriendsView>("/me/friends"));
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

	async function accept(id: number): Promise<void> {
		applyView(await useApi().post<FriendsView>(`/me/friends/${id}/accept`));
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
	}

	return { code, friends, received, sent, loaded, load, invite, qrToken, accept, remove, reset };
}
