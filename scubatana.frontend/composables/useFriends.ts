import { normalizeFriendCodeInput, type FriendInvitation, type Friendship, type FriendsView } from "~/utils/friends";

export type InviteOutcome = "sent" | "mutual";

export function useFriends() {
	const code = useState<string>("friends-code", () => "");
	const friends = useState<Friendship[]>("friends-list", () => []);
	const received = useState<FriendInvitation[]>("friends-received", () => []);
	const sent = useState<FriendInvitation[]>("friends-sent", () => []);
	const loaded = useState<boolean>("friends-loaded", () => false);

	function applyView(view: FriendsView): void {
		code.value = view.code;
		friends.value = view.friends;
		received.value = view.received;
		sent.value = view.sent;
		loaded.value = true;

		const { player } = usePlayer();

		if (player.value !== null) {
			player.value = {
				...player.value,
				stats: {
					...player.value.stats,
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

	return { code, friends, received, sent, loaded, load, invite, accept, remove, reset };
}
