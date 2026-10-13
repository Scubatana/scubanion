export const PENDING_EMAIL_TTL_MS = 10 * 60 * 1000;

export function serializePendingEmail(email: string, now: number): string {
	return JSON.stringify({ email, requestedAt: now });
}

export function readPendingEmail(raw: string | null, now: number): string | null {
	if (raw === null) {
		return null;
	}

	try {
		const parsed = JSON.parse(raw) as { email?: unknown; requestedAt?: unknown } | null;

		if (typeof parsed?.email !== "string" || typeof parsed.requestedAt !== "number") {
			return null;
		}

		return now - parsed.requestedAt < PENDING_EMAIL_TTL_MS ? parsed.email : null;
	} catch {
		return null;
	}
}
