export interface HistoryDay {
	day: string;
	steps: number;
	goal: number;
	goal_reached: boolean;
}

export interface HistoryStats {
	total: number;
	average: number;
	active_days: number;
	goal_days: number;
	best: { day: string; steps: number } | null;
	longest_streak: number;
}

export interface StepHistory {
	month: string;
	goal: number;
	has_previous: boolean;
	has_next: boolean;
	streak: { days: number; active: boolean };
	days: HistoryDay[];
	stats: HistoryStats;
}

export interface CalendarCell {
	key: string;
	day: string | null;
	number: number | null;
	steps: number | null;
	fill: number;
	reached: boolean;
}

export function shiftMonth(month: string, offset: number): string {
	const [year, monthNumber] = month.split("-").map(Number);
	const shifted = new Date(year, monthNumber - 1 + offset, 1);

	return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, "0")}`;
}

export function monthLabel(month: string): string {
	const [year, monthNumber] = month.split("-").map(Number);
	const label = new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" }).format(new Date(year, monthNumber - 1, 1));

	return `${label.charAt(0).toUpperCase()}${label.slice(1)}`;
}

export function longDayLabel(day: string): string {
	const [year, monthNumber, dayNumber] = day.split("-").map(Number);
	const label = new Intl.DateTimeFormat("fr-FR", { weekday: "long", day: "numeric", month: "long" }).format(new Date(year, monthNumber - 1, dayNumber));

	return `${label.charAt(0).toUpperCase()}${label.slice(1)}`;
}

export function shortDayLabel(day: string): string {
	const [year, monthNumber, dayNumber] = day.split("-").map(Number);
	const weekday = new Intl.DateTimeFormat("fr-FR", { weekday: "short" }).format(new Date(year, monthNumber - 1, dayNumber)).replace(".", "");

	return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${dayNumber}`;
}

export function calendarCells(month: string, days: HistoryDay[], goal: number): CalendarCell[] {
	const [year, monthNumber] = month.split("-").map(Number);
	const leadingBlanks = (new Date(year, monthNumber - 1, 1).getDay() + 6) % 7;
	const daysInMonth = new Date(year, monthNumber, 0).getDate();
	const byDay = new Map(days.map((entry) => [entry.day, entry]));
	const cells: CalendarCell[] = [];

	for (let blank = 0; blank < leadingBlanks; blank++) {
		cells.push({ key: `blank-${blank}`, day: null, number: null, steps: null, fill: 0, reached: false });
	}

	for (let number = 1; number <= daysInMonth; number++) {
		const day = `${month}-${String(number).padStart(2, "0")}`;
		const entry = byDay.get(day);

		cells.push({
			key: day,
			day,
			number,
			steps: entry ? entry.steps : null,
			fill: entry && (entry.goal ?? goal) > 0 ? Math.min(100, Math.round((entry.steps / (entry.goal ?? goal)) * 100)) : 0,
			reached: entry?.goal_reached ?? false
		});
	}

	return cells;
}

function longestStreak(days: HistoryDay[]): number {
	let longest = 0;
	let current = 0;

	for (const entry of days) {
		current = entry.goal_reached ? current + 1 : 0;
		longest = Math.max(longest, current);
	}

	return longest;
}

export function withLiveToday(history: StepHistory, today: string, liveSteps: number): StepHistory {
	const index = history.days.findIndex((entry) => entry.day === today);

	if (index === -1 || liveSteps <= history.days[index].steps) {
		return history;
	}

	const wasReached = history.days[index].goal_reached;
	const days = history.days.map((entry, position) => (position === index ? { ...entry, steps: liveSteps, goal_reached: liveSteps >= entry.goal } : entry));
	const total = days.reduce((sum, entry) => sum + entry.steps, 0);
	const best = days.reduce<HistoryDay | null>((top, entry) => (entry.steps > 0 && (top === null || entry.steps > top.steps) ? entry : top), null);
	const justReached = !wasReached && days[index].goal_reached;

	return {
		...history,
		days,
		streak: justReached ? { days: history.streak.days + 1, active: true } : history.streak,
		stats: {
			total,
			average: Math.round(total / days.length),
			active_days: days.filter((entry) => entry.steps > 0).length,
			goal_days: days.filter((entry) => entry.goal_reached).length,
			best: best ? { day: best.day, steps: best.steps } : null,
			longest_streak: longestStreak(days)
		}
	};
}
