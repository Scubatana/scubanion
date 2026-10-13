import { beforeEach, describe, expect, it, vi } from "vitest";

const storage = vi.hoisted(() => new Map<string, string>());

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

describe("useSession", () => {
	beforeEach(() => {
		storage.clear();
		clearNuxtState();
	});

	it("starts without token when nothing is stored", async () => {
		const session = useSession();

		await session.restore();

		expect(session.token.value).toBeNull();
		expect(session.profileCompleted.value).toBe(false);
	});

	it("stores the session on the device when it opens", async () => {
		await useSession().open("jeton-1", false);

		expect(storage.get("session.token")).toBe("jeton-1");
		expect(storage.get("session.profileCompleted")).toBe("false");
	});

	it("restores the stored session after a restart", async () => {
		await useSession().open("jeton-1", true);
		clearNuxtState();

		const session = useSession();
		await session.restore();

		expect(session.token.value).toBe("jeton-1");
		expect(session.profileCompleted.value).toBe(true);
	});

	it("remembers that the profile has been completed", async () => {
		const session = useSession();
		await session.open("jeton-1", false);

		await session.markProfileCompleted(true);

		expect(session.profileCompleted.value).toBe(true);
		expect(storage.get("session.profileCompleted")).toBe("true");
	});

	it("erases everything when the session ends", async () => {
		const session = useSession();
		await session.open("jeton-1", true);

		await session.clear();

		expect(session.token.value).toBeNull();
		expect(session.profileCompleted.value).toBe(false);
		expect(storage.size).toBe(0);
	});
});
