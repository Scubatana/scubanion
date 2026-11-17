import { Preferences } from "@capacitor/preferences";
import { parseAlbum, withPhoto, type QuestPhoto } from "~/utils/photos";

const KEY_PREFIX = "quests.photos.";

// Album local des photos de sortie, un par joueur sur ce téléphone : un autre compte connecté
// sur le même téléphone ne le voit pas, et il est effacé avec le compte.
export function usePhotos() {
	const album = useState<QuestPhoto[]>("quest-photos", () => []);
	const owner = useState<number | null>("quest-photos-owner", () => null);

	function storageKey(): string | null {
		const playerId = usePlayer().player.value?.id;

		return playerId === undefined ? null : `${KEY_PREFIX}${playerId}`;
	}

	async function load(): Promise<void> {
		const playerId = usePlayer().player.value?.id ?? null;
		const key = storageKey();

		if (key === null || owner.value === playerId) {
			return;
		}

		album.value = parseAlbum((await Preferences.get({ key })).value);
		owner.value = playerId;
	}

	async function add(photo: QuestPhoto): Promise<void> {
		await load();
		album.value = withPhoto(album.value, photo);
		const key = storageKey();

		if (key !== null) {
			await Preferences.set({ key, value: JSON.stringify(album.value) });
		}
	}

	function reset(): void {
		album.value = [];
		owner.value = null;
	}

	async function discard(): Promise<void> {
		const key = storageKey();
		reset();

		if (key !== null) {
			await Preferences.remove({ key });
		}
	}

	return { album, load, add, reset, discard };
}
