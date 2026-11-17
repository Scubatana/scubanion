import { beforeEach, describe, expect, it, vi } from "vitest";
import { fitWithin, MAX_PHOTOS, parseAlbum, photoDate, withPhoto, type QuestPhoto } from "~/utils/photos";

const { storage } = vi.hoisted(() => ({ storage: new Map<string, string>() }));

vi.mock("@capacitor/preferences", () => ({
	Preferences: {
		get: async ({ key }: { key: string }) => ({ value: storage.get(key) ?? null }),
		set: async ({ key, value }: { key: string; value: string }) => {
			storage.set(key, value);
		},
		remove: async ({ key }: { key: string }) => {
			storage.delete(key);
		}
	}
}));

function photo(id: string, takenAt = "2026-11-17T14:00:00.000Z"): QuestPhoto {
	return { id, quest: "Souvenirs de balade", label: "Prends une photo dans un parc", taken_at: takenAt, thumbnail: "data:image/jpeg;base64,AAAA" };
}

function signIn(id: number): void {
	usePlayer().player.value = {
		id,
		email: `joueur${id}@example.com`,
		pseudo: `Joueur ${id}`,
		birth_date: "2004-05-12",
		gender: null,
		ambition: "go_out",
		profile_completed: true,
		stats: { total_steps: 0, quests_done: 0, friends_count: 0 }
	};
}

describe("photos utils", () => {
	it("puts the newest photo first, replaces a photo with the same id and keeps the album small", () => {
		const album = withPhoto([photo("a"), photo("b")], photo("c"));

		expect(album.map((entry) => entry.id)).toEqual(["c", "a", "b"]);
		expect(withPhoto(album, photo("a")).map((entry) => entry.id)).toEqual(["a", "c", "b"]);

		const full = Array.from({ length: MAX_PHOTOS }, (_, index) => photo(String(index)));
		const added = withPhoto(full, photo("new"));

		expect(added).toHaveLength(MAX_PHOTOS);
		expect(added[0].id).toBe("new");
		expect(added.map((entry) => entry.id)).not.toContain(String(MAX_PHOTOS - 1));
	});

	it("reads back a stored album and ignores anything malformed", () => {
		expect(parseAlbum(JSON.stringify([photo("a")]))).toEqual([photo("a")]);
		expect(parseAlbum(null)).toEqual([]);
		expect(parseAlbum("pas du json")).toEqual([]);
		expect(parseAlbum(JSON.stringify({ id: "a" }))).toEqual([]);
		expect(parseAlbum(JSON.stringify([photo("a"), { ...photo("b"), thumbnail: "https://exemple.fr/photo.jpg" }, { id: 3 }]))).toEqual([photo("a")]);
	});

	it("shrinks a picture to fit a square without enlarging a small one", () => {
		expect(fitWithin(4000, 3000, 480)).toEqual({ width: 480, height: 360 });
		expect(fitWithin(3000, 4000, 480)).toEqual({ width: 360, height: 480 });
		expect(fitWithin(300, 200, 480)).toEqual({ width: 300, height: 200 });
		expect(fitWithin(0, 0, 480)).toEqual({ width: 1, height: 1 });
	});

	it("dates a photo in French, at the time of Paris", () => {
		expect(photoDate("2026-11-17T23:30:00.000Z")).toBe("18 nov.");
	});
});

describe("usePhotos", () => {
	beforeEach(() => {
		storage.clear();
		clearNuxtState();
	});

	it("keeps one album per player on the phone", async () => {
		signIn(1);
		await usePhotos().add(photo("a"));

		expect(usePhotos().album.value.map((entry) => entry.id)).toEqual(["a"]);
		expect(JSON.parse(storage.get("quests.photos.1") ?? "[]")).toHaveLength(1);

		usePhotos().reset();
		signIn(2);
		await usePhotos().load();

		expect(usePhotos().album.value).toEqual([]);

		usePhotos().reset();
		signIn(1);
		await usePhotos().load();

		expect(usePhotos().album.value.map((entry) => entry.id)).toEqual(["a"]);
	});

	it("erases the album of a deleted account from the phone", async () => {
		signIn(1);
		await usePhotos().add(photo("a"));

		await usePhotos().discard();

		expect(usePhotos().album.value).toEqual([]);
		expect(storage.has("quests.photos.1")).toBe(false);
	});

	it("does not store anything without a signed-in player", async () => {
		await usePhotos().add(photo("a"));

		expect(storage.size).toBe(0);
	});
});
