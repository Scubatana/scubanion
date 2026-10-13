import { describe, expect, it } from "vitest";
import { GOAL_MAX, GOAL_MIN, GOAL_STEP, shiftedGoal } from "~/utils/stepGoal";

describe("shiftedGoal", () => {
	it("moves the goal by one notch in either direction", () => {
		expect(shiftedGoal(5000, GOAL_STEP)).toBe(5500);
		expect(shiftedGoal(5000, -GOAL_STEP)).toBe(4500);
	});

	it("reaches the bounds without going past them", () => {
		expect(shiftedGoal(GOAL_MIN + GOAL_STEP, -GOAL_STEP)).toBe(GOAL_MIN);
		expect(shiftedGoal(GOAL_MAX - GOAL_STEP, GOAL_STEP)).toBe(GOAL_MAX);
		expect(shiftedGoal(GOAL_MIN, -GOAL_STEP)).toBeNull();
		expect(shiftedGoal(GOAL_MAX, GOAL_STEP)).toBeNull();
	});
});
