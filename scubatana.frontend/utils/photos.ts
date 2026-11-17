// Photos de sortie (tâches `photo` des quêtes). Elles ne quittent jamais le téléphone : seule une
// miniature est gardée dans l'album local du joueur, et le serveur ne reçoit que la position.

export interface QuestPhoto {
	id: string;
	quest: string;
	label: string;
	taken_at: string;
	thumbnail: string;
}

export const MAX_PHOTOS = 24;

export const THUMBNAIL_SIZE = 480;

export const PHOTO_ERROR = "Impossible de lire cette photo. Réessaie avec l'appareil photo.";

export function withPhoto(album: QuestPhoto[], photo: QuestPhoto): QuestPhoto[] {
	return [photo, ...album.filter((entry) => entry.id !== photo.id)].slice(0, MAX_PHOTOS);
}

export function parseAlbum(raw: string | null): QuestPhoto[] {
	try {
		const parsed: unknown = JSON.parse(raw ?? "[]");

		return Array.isArray(parsed) ? parsed.filter(isPhoto).slice(0, MAX_PHOTOS) : [];
	} catch {
		return [];
	}
}

function isPhoto(value: unknown): value is QuestPhoto {
	if (typeof value !== "object" || value === null) {
		return false;
	}

	const photo = value as Record<string, unknown>;

	return ["id", "quest", "label", "taken_at", "thumbnail"].every((field) => typeof photo[field] === "string")
		&& String(photo.thumbnail).startsWith("data:image/");
}

export function fitWithin(width: number, height: number, max: number): { width: number; height: number } {
	const scale = Math.min(1, max / Math.max(width, height, 1));

	return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
}

export function photoDate(isoDate: string): string {
	return new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "short", timeZone: "Europe/Paris" }).format(new Date(isoDate));
}

export async function thumbnailOf(file: Blob, max = THUMBNAIL_SIZE): Promise<string> {
	try {
		const bitmap = await createImageBitmap(file);
		const { width, height } = fitWithin(bitmap.width, bitmap.height, max);
		const canvas = document.createElement("canvas");
		canvas.width = width;
		canvas.height = height;
		canvas.getContext("2d")?.drawImage(bitmap, 0, 0, width, height);
		bitmap.close();

		return canvas.toDataURL("image/jpeg", 0.75);
	} catch {
		throw new Error(PHOTO_ERROR);
	}
}
