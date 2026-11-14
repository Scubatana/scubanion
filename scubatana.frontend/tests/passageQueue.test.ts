import { describe, expect, it } from "vitest";
import { isPassageExpired, PASSAGE_MAX_AGE_MS, parsePassageQueue, withoutPassage, withPassage, type PendingPassage } from "~/utils/passageQueue";

const now = Date.parse("2026-11-14T12:00:00.000Z");

function passage(token: string, minutesAgo: number, extra: Partial<PendingPassage> = {}): PendingPassage {
	return { token, recorded_at: new Date(now - minutesAgo * 60_000).toISOString(), ...extra };
}

describe("offline passage queue", () => {
	it("reads only well-formed entries from storage", () => {
		const stored = JSON.stringify([
			passage("chateau-01.t3k2w0.AbCdEfGhIjKlMnOp", 5),
			{ token: 42, recorded_at: "2026-11-14T11:00:00.000Z" },
			{ token: "sans-date" },
			null,
			"texte"
		]);

		expect(parsePassageQueue(stored)).toEqual([passage("chateau-01.t3k2w0.AbCdEfGhIjKlMnOp", 5)]);
		expect(parsePassageQueue(null)).toEqual([]);
		expect(parsePassageQueue("{\"token\":\"un objet, pas une liste\"}")).toEqual([]);
	});

	it("keeps a passage exactly 24 hours old, drops one just older, and drops an unreadable date", () => {
		expect(isPassageExpired({ token: "a", recorded_at: new Date(now - PASSAGE_MAX_AGE_MS).toISOString() }, now)).toBe(false);
		expect(isPassageExpired({ token: "a", recorded_at: new Date(now - PASSAGE_MAX_AGE_MS - 1).toISOString() }, now)).toBe(true);
		expect(isPassageExpired({ token: "a", recorded_at: "hier soir" }, now)).toBe(true);
	});

	it("keeps a single entry per token, the latest one with its position", () => {
		const first = passage("chateau-01.t3k2w0.AbCdEfGhIjKlMnOp", 10);
		const other = passage("port-02.t3k2w1.QrStUvWxYz012345", 8);
		const again = passage("chateau-01.t3k2w0.AbCdEfGhIjKlMnOp", 2, { latitude: 43.69, longitude: 7.28, accuracy_m: 9 });

		const queue = withPassage(withPassage(withPassage([], first), other), again);

		expect(queue).toEqual([other, again]);
		expect(withoutPassage(queue, other.token)).toEqual([again]);
		expect(withoutPassage(queue, "inconnu.t0.AbCdEfGhIjKlMnOp")).toEqual(queue);
	});
});
