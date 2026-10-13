import { describe, expect, it } from "vitest";
import { ApiError, readableMessage, toApiError } from "~/utils/apiError";
import { parseBody } from "~/utils/http";
import { formatCount, formatDay, latestBirthDate } from "~/utils/labels";

describe("toApiError", () => {
	it("keeps the first message of each invalid field", () => {
		const error = toApiError(422, { errors: { pseudo: ["Ce pseudo est déjà pris.", "Autre"], code: ["Code faux"] } });

		expect(error.status).toBe(422);
		expect(error.fields).toEqual({ pseudo: "Ce pseudo est déjà pris.", code: "Code faux" });
	});

	it("explains a throttled request", () => {
		expect(toApiError(429, { message: "Too Many Attempts." }).message).toContain("Trop de tentatives");
	});

	it("survives a body that is empty or not an object", () => {
		expect(toApiError(500, null).fields).toEqual({});
		expect(toApiError(502, "<html>Bad Gateway</html>").message).toBe("Une erreur est survenue. Réessaie.");
	});
});

describe("readableMessage", () => {
	it("returns the message of an ApiError and a generic sentence otherwise", () => {
		expect(readableMessage(new ApiError(0, "Serveur injoignable"))).toBe("Serveur injoignable");
		expect(readableMessage(new TypeError("boom"))).toBe("Une erreur est survenue. Réessaie.");
	});
});

describe("parseBody", () => {
	it("parses JSON text and passes parsed values through", () => {
		expect(parseBody('{"token":"abc"}')).toEqual({ token: "abc" });
		expect(parseBody({ token: "abc" })).toEqual({ token: "abc" });
	});

	it("returns null for an empty or non JSON body", () => {
		expect(parseBody("")).toBeNull();
		expect(parseBody(undefined)).toBeNull();
		expect(parseBody("<html></html>")).toBeNull();
	});
});

describe("labels", () => {
	it("formats counts with a thousands separator", () => {
		expect(formatCount(4820).replace(/\s/g, " ")).toBe("4 820");
		expect(formatCount(0)).toBe("0");
	});

	it("formats a day as a capitalized weekday and a two digit day", () => {
		expect(formatDay("2026-10-12")).toBe("Lundi 12");
		expect(formatDay("2026-11-01")).toBe("Dimanche 01");
	});

	it("computes the latest birth date for the minimum age", () => {
		expect(latestBirthDate(new Date(2026, 9, 12), 15)).toBe("2011-10-12");
	});
});
