import { describe, expect, it } from "vitest";
import { toCurrentPosition } from "~/utils/geolocation";

describe("toCurrentPosition", () => {
	it("keeps the accuracy in whole meters, the mocked flag and the time of the fix", () => {
		expect(toCurrentPosition({ latitude: 43.6953, longitude: 7.2806, accuracy: 12.6, mocked: true, time: Date.UTC(2026, 9, 13, 18, 0) })).toEqual({
			latitude: 43.6953,
			longitude: 7.2806,
			accuracy_m: 13,
			mocked: true,
			recorded_at: "2026-10-13T18:00:00.000Z"
		});
	});
});
