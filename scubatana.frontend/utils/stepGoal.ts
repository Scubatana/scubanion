export const GOAL_STEP = 500;

export const GOAL_MIN = 1000;

export const GOAL_MAX = 50000;

export function shiftedGoal(goal: number, offset: number): number | null {
	const next = goal + offset;

	return next < GOAL_MIN || next > GOAL_MAX ? null : next;
}
