import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { httpRequest, navigateTo, storage } = vi.hoisted(() => ({
	httpRequest: vi.fn(),
	navigateTo: vi.fn(),
	storage: new Map<string, string>()
}));

vi.mock("~/utils/http", () => ({ httpRequest }));

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

mockNuxtImport("navigateTo", () => navigateTo);

const newPlayer = {
	id: 1,
	email: "lea@example.com",
	pseudo: null,
	birth_date: null,
	gender: null,
	ambition: null,
	profile_completed: false,
	stats: { total_steps: 0, quests_done: 0, friends_count: 0 }
};

describe("useAuth", () => {
	beforeEach(() => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
	});

	it("normalizes the email and remembers it for the verification screen", async () => {
		httpRequest.mockResolvedValue({ status: 204, data: null });
		const auth = useAuth();

		await auth.requestCode("  Lea@Example.COM ");

		expect(httpRequest.mock.calls[0][3]).toEqual({ email: "lea@example.com" });
		expect(auth.pendingEmail.value).toBe("lea@example.com");
	});

	it("keeps no pending email when the request fails", async () => {
		httpRequest.mockResolvedValue({ status: 422, data: { errors: { email: ["invalide"] } } });
		const auth = useAuth();

		await expect(auth.requestCode("pas-un-email")).rejects.toMatchObject({ status: 422 });

		expect(auth.pendingEmail.value).toBeNull();
	});

	it("opens the session with the profile flag returned by the server", async () => {
		const auth = useAuth();
		httpRequest.mockResolvedValueOnce({ status: 204, data: null });
		await auth.requestCode("lea@example.com");
		httpRequest.mockResolvedValueOnce({ status: 200, data: { token: "jeton-1", player: newPlayer } });

		await auth.verifyCode("123456", true);

		expect(httpRequest.mock.calls[1][3]).toEqual({ email: "lea@example.com", code: "123456", terms_accepted: true });
		expect(useSession().token.value).toBe("jeton-1");
		expect(useSession().profileCompleted.value).toBe(false);
		expect(storage.get("session.token")).toBe("jeton-1");
		expect(usePlayer().player.value?.email).toBe("lea@example.com");
		expect(auth.pendingEmail.value).toBeNull();
	});

	it("opens no session when the code is refused", async () => {
		const auth = useAuth();
		httpRequest.mockResolvedValueOnce({ status: 204, data: null });
		await auth.requestCode("lea@example.com");
		httpRequest.mockResolvedValueOnce({ status: 422, data: { errors: { code: ["Ce code est incorrect ou expiré."] } } });

		await expect(auth.verifyCode("000000", true)).rejects.toMatchObject({ status: 422 });

		expect(useSession().token.value).toBeNull();
		expect(auth.pendingEmail.value).toBe("lea@example.com");
	});

	it("revokes the token on the server and clears the session on logout", async () => {
		await useSession().open("jeton-1", true);
		usePlayer().player.value = { ...newPlayer, profile_completed: true };
		httpRequest.mockResolvedValue({ status: 204, data: null });

		await useAuth().logout();

		expect(httpRequest.mock.calls[0][1]).toMatch(/\/api\/auth\/logout$/);
		expect(httpRequest.mock.calls[0][2].Authorization).toBe("Bearer jeton-1");
		expect(useSession().token.value).toBeNull();
		expect(usePlayer().player.value).toBeNull();
		expect(storage.size).toBe(0);
	});

	it("clears the session on logout even when the server is unreachable", async () => {
		await useSession().open("jeton-1", true);
		httpRequest.mockRejectedValue(new TypeError("Failed to fetch"));

		await useAuth().logout();

		expect(useSession().token.value).toBeNull();
		expect(storage.size).toBe(0);
	});
});
