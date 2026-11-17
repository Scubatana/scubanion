import { describe, expect, it } from "vitest";
import { PULL_MAX, PULL_THRESHOLD, pullOffset, shouldRefresh } from "~/utils/pullToRefresh";

describe("pull to refresh", () => {
	it("follows half of the finger, never upwards and never past the maximum", () => {
		expect(pullOffset(-40)).toBe(0);
		expect(pullOffset(0)).toBe(0);
		expect(pullOffset(60)).toBe(30);
		expect(pullOffset(1000)).toBe(PULL_MAX);
	});

	it("refreshes only once the indicator reaches the threshold", () => {
		expect(shouldRefresh(PULL_THRESHOLD - 1)).toBe(false);
		expect(shouldRefresh(PULL_THRESHOLD)).toBe(true);
		expect(shouldRefresh(pullOffset(PULL_THRESHOLD * 2))).toBe(true);
	});
});
