import { mockNuxtImport, mountSuspended } from "@nuxt/test-utils/runtime";
import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import LoginPage from "~/pages/login.vue";
import VerificationPage from "~/pages/verification.vue";
import { UNREACHABLE_MESSAGE } from "~/utils/apiError";
import { serializePendingEmail } from "~/utils/pendingEmail";
import type { Player } from "~/utils/types";

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

const lea: Player = {
	id: 7,
	email: "lea@example.com",
	pseudo: "Lea",
	birth_date: "2004-05-12",
	gender: null,
	ambition: "fitness",
	profile_completed: true,
	stats: { total_steps: 0, quests_done: 0, friends_count: 0 }
};

function calls(): Array<[string, string, unknown]> {
	return httpRequest.mock.calls.map(([method, url, , body]) => [method, String(url).replace(/^.*\/api/, ""), body]);
}

function validationError(field: string, message: string) {
	return { status: 422, data: { message, errors: { [field]: [message] } } };
}

describe("page de connexion", () => {
	beforeEach(() => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		storage.clear();
		clearNuxtState();
	});

	it("asks for a code for the normalized email, then goes to the verification", async () => {
		httpRequest.mockResolvedValue({ status: 204, data: null });
		const wrapper = await mountSuspended(LoginPage);

		await wrapper.find("#email").setValue("  Lea@Example.com ");
		await wrapper.find("form").trigger("submit");
		await flushPromises();

		expect(calls()).toEqual([["POST", "/auth/code", { email: "lea@example.com" }]]);
		expect(useAuth().pendingEmail.value).toBe("lea@example.com");
		expect(navigateTo).toHaveBeenCalledWith("/verification");
	});

	it("shows an invalid email under the field", async () => {
		httpRequest.mockResolvedValue(validationError("email", "The email field must be a valid email address."));
		const wrapper = await mountSuspended(LoginPage);

		await wrapper.find("#email").setValue("pas-un-email");
		await wrapper.find("form").trigger("submit");
		await flushPromises();

		expect(wrapper.find(".field-error").text()).toBe("Cette adresse email est invalide.");
		expect(wrapper.find(".form-error").exists()).toBe(false);
		expect(navigateTo).not.toHaveBeenCalled();
	});

	it("explains that the server cannot be reached", async () => {
		httpRequest.mockRejectedValue(new Error("offline"));
		const wrapper = await mountSuspended(LoginPage);

		await wrapper.find("#email").setValue("lea@example.com");
		await wrapper.find("form").trigger("submit");
		await flushPromises();

		expect(wrapper.find(".form-error").text()).toBe(UNREACHABLE_MESSAGE);
		expect(wrapper.find("button[type=submit]").attributes("disabled")).toBeUndefined();
	});
});

describe("page de vérification du code", () => {
	// L'adresse en attente est gardée sur le téléphone et relue à l'ouverture de l'app, comme le fait le middleware.
	async function askedCodeFor(email: string | null): Promise<void> {
		storage.clear();

		if (email !== null) {
			storage.set("auth.pendingEmail", serializePendingEmail(email, Date.now()));
		}

		await useAuth().restorePendingEmail();
	}

	beforeEach(async () => {
		httpRequest.mockReset();
		navigateTo.mockReset();
		clearNuxtState();
		await askedCodeFor("lea@example.com");
	});

	it("sends back to the login page when no code was asked", async () => {
		clearNuxtState();
		await askedCodeFor(null);

		await mountSuspended(VerificationPage);

		expect(navigateTo).toHaveBeenCalledWith("/login", { replace: true });
	});

	it("opens the session with a right code and the terms accepted", async () => {
		httpRequest.mockResolvedValue({ status: 200, data: { token: "jeton-7", player: lea } });
		const wrapper = await mountSuspended(VerificationPage);

		expect(wrapper.text()).toContain("lea@example.com");

		await wrapper.find("#code").setValue("123456");
		await wrapper.find("input[type=checkbox]").setValue(true);
		await wrapper.find("form").trigger("submit");
		await flushPromises();

		expect(calls()).toEqual([["POST", "/auth/verify", { email: "lea@example.com", code: "123456", terms_accepted: true }]]);
		expect(useSession().token.value).toBe("jeton-7");
		expect(usePlayer().player.value?.pseudo).toBe("Lea");
		expect(useAuth().pendingEmail.value).toBeNull();
		expect(navigateTo).toHaveBeenCalledWith("/", { replace: true });
	});

	it("tells which field is wrong: the code, or the terms not accepted", async () => {
		httpRequest.mockResolvedValueOnce(validationError("code", "Code invalide."));
		const wrapper = await mountSuspended(VerificationPage);

		await wrapper.find("#code").setValue("000000");
		await wrapper.find("form").trigger("submit");
		await flushPromises();

		expect(wrapper.findAll(".field-error").map((error) => error.text())).toEqual(["Ce code est incorrect ou expiré."]);

		httpRequest.mockResolvedValueOnce(validationError("terms_accepted", "Conditions requises."));
		await wrapper.find("form").trigger("submit");
		await flushPromises();

		expect(wrapper.findAll(".field-error").map((error) => error.text())).toEqual(["Accepte les conditions d'utilisation pour créer ton compte."]);
		expect(useSession().token.value).toBeNull();
		expect(navigateTo).not.toHaveBeenCalled();
	});

	it("sends a new code and clears the one typed", async () => {
		httpRequest.mockResolvedValue({ status: 204, data: null });
		const wrapper = await mountSuspended(VerificationPage);

		await wrapper.find("#code").setValue("999999");
		await wrapper.find("button.link-under").trigger("click");
		await flushPromises();

		expect(calls()).toEqual([["POST", "/auth/code", { email: "lea@example.com" }]]);
		expect(wrapper.find(".form-notice").text()).toBe("Un nouveau code vient d'être envoyé.");
		expect((wrapper.find("#code").element as HTMLInputElement).value).toBe("");
	});
});
