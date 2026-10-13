import { describe, expect, it } from "vitest";
import { calendarCells, monthLabel, shiftMonth, withLiveToday, type StepHistory } from "~/utils/stepHistory";

describe("calendarCells", () => {
	const days = [
		{ day: "2026-10-01", steps: 2500, goal: 5000, goal_reached: false },
		{ day: "2026-10-02", steps: 8000, goal: 5000, goal_reached: true }
	];

	it("lines the first day up under its weekday, the week starting on Monday", () => {
		const cells = calendarCells("2026-10", days, 5000);

		expect(cells.slice(0, 3).every((cell) => cell.day === null)).toBe(true);
		expect(cells[3].day).toBe("2026-10-01");
		expect(cells).toHaveLength(3 + 31);
	});

	it("fills each day in proportion to the goal, never beyond full", () => {
		const cells = calendarCells("2026-10", days, 5000);

		expect(cells[3]).toMatchObject({ number: 1, steps: 2500, fill: 50, reached: false });
		expect(cells[4]).toMatchObject({ number: 2, steps: 8000, fill: 100, reached: true });
	});

	it("leaves days the server did not send, such as future days, without steps", () => {
		expect(calendarCells("2026-10", days, 5000)[5]).toMatchObject({ day: "2026-10-03", steps: null, fill: 0, reached: false });
	});
});

describe("months", () => {
	it("moves across year boundaries", () => {
		expect(shiftMonth("2026-01", -1)).toBe("2025-12");
		expect(shiftMonth("2026-12", 1)).toBe("2027-01");
	});

	it("names a month in French", () => {
		expect(monthLabel("2026-10")).toBe("Octobre 2026");
	});
});

describe("withLiveToday", () => {
	const history: StepHistory = {
		month: "2026-10",
		goal: 5000,
		has_previous: false,
		has_next: false,
		streak: { days: 1, active: false },
		days: [
			{ day: "2026-10-11", steps: 1000, goal: 5000, goal_reached: false },
			{ day: "2026-10-12", steps: 6000, goal: 5000, goal_reached: true },
			{ day: "2026-10-13", steps: 200, goal: 5000, goal_reached: false }
		],
		stats: { total: 7200, average: 2400, active_days: 3, goal_days: 1, best: { day: "2026-10-12", steps: 6000 }, longest_streak: 1 }
	};

	it("shows the steps counted on the phone before the server knows them", () => {
		const live = withLiveToday(history, "2026-10-13", 1700);

		expect(live.days[2]).toEqual({ day: "2026-10-13", steps: 1700, goal: 5000, goal_reached: false });
		expect(live.stats.total).toBe(8700);
		expect(live.stats.average).toBe(2900);
		expect(live.streak).toEqual({ days: 1, active: false });
	});

	it("lights the streak and updates the records the moment the goal is reached", () => {
		const live = withLiveToday(history, "2026-10-13", 6500);

		expect(live.days[2].goal_reached).toBe(true);
		expect(live.streak).toEqual({ days: 2, active: true });
		expect(live.stats).toMatchObject({ goal_days: 2, longest_streak: 2, best: { day: "2026-10-13", steps: 6500 } });
	});

	it("leaves another month, or a total the server already has, untouched", () => {
		expect(withLiveToday(history, "2026-11-02", 900)).toBe(history);
		expect(withLiveToday(history, "2026-10-13", 150)).toBe(history);
	});
});
