import { describe, expect, it } from "vitest";
import { resolveRedirect } from "~/utils/sessionRedirect";

describe("resolveRedirect", () => {
	it("sends a visitor without token to the login screen", () => {
		expect(resolveRedirect("/", false, false)).toBe("/login");
		expect(resolveRedirect("/compte", false, false)).toBe("/login");
		expect(resolveRedirect("/profil", false, false)).toBe("/login");
	});

	it("lets a visitor without token reach the login and verification screens", () => {
		expect(resolveRedirect("/login", false, false)).toBeNull();
		expect(resolveRedirect("/verification", false, false)).toBeNull();
	});

	it("keeps a player with an incomplete profile on the profile screen", () => {
		expect(resolveRedirect("/", true, false)).toBe("/profil");
		expect(resolveRedirect("/login", true, false)).toBe("/profil");
		expect(resolveRedirect("/quetes", true, false)).toBe("/profil");
		expect(resolveRedirect("/profil", true, false)).toBeNull();
	});

	it("sends a player with a complete profile away from the onboarding screens", () => {
		expect(resolveRedirect("/login", true, true)).toBe("/");
		expect(resolveRedirect("/verification", true, true)).toBe("/");
		expect(resolveRedirect("/profil", true, true)).toBe("/");
	});

	it("lets a player with a complete profile browse the app", () => {
		expect(resolveRedirect("/", true, true)).toBeNull();
		expect(resolveRedirect("/compte", true, true)).toBeNull();
		expect(resolveRedirect("/amis", true, true)).toBeNull();
	});
});
