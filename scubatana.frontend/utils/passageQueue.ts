export interface PendingPassage {
	token: string;
	recorded_at: string;
	latitude?: number;
	longitude?: number;
	accuracy_m?: number;
	mocked?: boolean;
}

export const PASSAGE_MAX_AGE_MS = 24 * 60 * 60 * 1000;

export function parsePassageQueue(raw: string | null): PendingPassage[] {
	if (raw === null) {
		return [];
	}

	try {
		const parsed = JSON.parse(raw) as unknown;

		return Array.isArray(parsed)
			? parsed.filter((entry): entry is PendingPassage => (
				typeof entry === "object"
				&& entry !== null
				&& typeof (entry as PendingPassage).token === "string"
				&& typeof (entry as PendingPassage).recorded_at === "string"
			))
			: [];
	} catch {
		return [];
	}
}

export function isPassageExpired(entry: PendingPassage, nowMs: number = Date.now()): boolean {
	const scannedAt = new Date(entry.recorded_at).getTime();

	if (Number.isNaN(scannedAt)) {
		return true;
	}

	return nowMs - scannedAt > PASSAGE_MAX_AGE_MS;
}

export function pruneExpiredPassages(queue: PendingPassage[], nowMs: number = Date.now()): PendingPassage[] {
	return queue.filter((entry) => !isPassageExpired(entry, nowMs));
}

export function withPassage(queue: PendingPassage[], entry: PendingPassage): PendingPassage[] {
	return [...queue.filter((item) => item.token !== entry.token), entry];
}

export function withoutPassage(queue: PendingPassage[], token: string): PendingPassage[] {
	return queue.filter((item) => item.token !== token);
}
