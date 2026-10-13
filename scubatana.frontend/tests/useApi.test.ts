import { mockNuxtImport } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "~/utils/apiError";

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

describe("useApi", () => {
	beforeEach(() => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
	});

	it("calls the API under /api and returns the body", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { ok: true } });

		const body = await useApi().post<{ ok: boolean }>("/auth/code", { email: "lea@example.com" });

		expect(body).toEqual({ ok: true });
		const [method, url, headers, payload] = httpRequest.mock.calls[0];
		expect(method).toBe("POST");
		expect(url).toMatch(/\/api\/auth\/code$/);
		expect(headers.Accept).toBe("application/json");
		expect(headers.Authorization).toBeUndefined();
		expect(payload).toEqual({ email: "lea@example.com" });
	});

	it("attaches the session token once logged in", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: {} });
		await useSession().open("jeton-1", true);

		await useApi().get("/me");

		expect(httpRequest.mock.calls[0][2].Authorization).toBe("Bearer jeton-1");
	});

	it("ends the session and returns to the login screen when the token is refused", async () => {
		httpRequest.mockResolvedValue({ status: 401, data: { message: "Unauthenticated." } });
		const session = useSession();
		await session.open("jeton-revoque", true);

		await expect(useApi().get("/me")).rejects.toMatchObject({ status: 401 });

		expect(session.token.value).toBeNull();
		expect(storage.has("session.token")).toBe(false);
		expect(navigateTo).toHaveBeenCalledWith("/login");
	});

	it("exposes one message per invalid field", async () => {
		httpRequest.mockResolvedValue({ status: 422, data: { errors: { code: ["Ce code est incorrect ou expiré."] } } });

		const error = await useApi().post("/auth/verify", {}).catch((caught) => caught);

		expect(error).toBeInstanceOf(ApiError);
		expect(error.fields.code).toBe("Ce code est incorrect ou expiré.");
		expect(navigateTo).not.toHaveBeenCalled();
	});

	it("reports an unreachable server without ending the session", async () => {
		httpRequest.mockRejectedValue(new TypeError("Failed to fetch"));
		const session = useSession();
		await session.open("jeton-1", true);

		const error = await useApi().get("/me").catch((caught) => caught);

		expect(error.status).toBe(0);
		expect(error.message).toContain("Impossible de joindre le serveur");
		expect(session.token.value).toBe("jeton-1");
	});
});
