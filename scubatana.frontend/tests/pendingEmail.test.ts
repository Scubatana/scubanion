import { describe, expect, it } from "vitest";
import { PENDING_EMAIL_TTL_MS, readPendingEmail, serializePendingEmail } from "~/utils/pendingEmail";

const NOW = 1_800_000_000_000;

describe("readPendingEmail", () => {
	it("reads back the email while its code is still valid", () => {
		const stored = serializePendingEmail("lea@example.com", NOW);

		expect(readPendingEmail(stored, NOW)).toBe("lea@example.com");
		expect(readPendingEmail(stored, NOW + PENDING_EMAIL_TTL_MS - 1)).toBe("lea@example.com");
	});

	it("forgets the email once its code has expired", () => {
		expect(readPendingEmail(serializePendingEmail("lea@example.com", NOW), NOW + PENDING_EMAIL_TTL_MS)).toBeNull();
	});

	it("reads nothing when nothing or something unreadable is stored", () => {
		expect(readPendingEmail(null, NOW)).toBeNull();
		expect(readPendingEmail("pas du json", NOW)).toBeNull();
		expect(readPendingEmail("null", NOW)).toBeNull();
		expect(readPendingEmail(JSON.stringify({ email: "lea@example.com" }), NOW)).toBeNull();
	});
});
