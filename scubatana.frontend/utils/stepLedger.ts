export interface StepLedger {
	lastSensorCount: number | null;
	since: number | null;
	days: Record<string, number>;
	synced: Record<string, number>;
}

export interface StepEntry {
	day: string;
	steps: number;
}

export interface RecordedBucket {
	start: number;
	steps: number;
}

const KEPT_DAYS = 30;

const RECORDED_DAYS = 9;

export function emptyLedger(): StepLedger {
	return { lastSensorCount: null, since: null, days: {}, synced: {} };
}

export function localDay(date: Date): string {
	const month = String(date.getMonth() + 1).padStart(2, "0");
	const day = String(date.getDate()).padStart(2, "0");

	return `${date.getFullYear()}-${month}-${day}`;
}

export function parseLedger(raw: string | null): StepLedger {
	if (raw === null) {
		return emptyLedger();
	}

	try {
		const parsed = JSON.parse(raw) as Partial<StepLedger> | null;

		return {
			lastSensorCount: typeof parsed?.lastSensorCount === "number" ? parsed.lastSensorCount : null,
			since: typeof parsed?.since === "number" ? parsed.since : null,
			days: parsed?.days && typeof parsed.days === "object" ? parsed.days : {},
			synced: parsed?.synced && typeof parsed.synced === "object" ? parsed.synced : {}
		};
	} catch {
		return emptyLedger();
	}
}

export function oldestKeptDay(today: string): string {
	const [year, month, day] = today.split("-").map(Number);

	return localDay(new Date(year, month - 1, day - KEPT_DAYS));
}

function keepRecent(values: Record<string, number>, today: string): Record<string, number> {
	const oldest = oldestKeptDay(today);

	return Object.fromEntries(Object.entries(values).filter(([day]) => day >= oldest));
}

export function sensorDelta(lastSensorCount: number | null, sensorCount: number): number {
	if (lastSensorCount === null) {
		return 0;
	}

	return sensorCount >= lastSensorCount ? sensorCount - lastSensorCount : sensorCount;
}

export function resume(ledger: StepLedger, now: number): StepLedger {
	return ledger.since === null ? { ...ledger, since: now, lastSensorCount: null } : ledger;
}

export function suspend(ledger: StepLedger): StepLedger {
	return { ...ledger, since: null, lastSensorCount: null };
}

export function applyReading(ledger: StepLedger, sensorCount: number, today: string): StepLedger {
	const delta = sensorDelta(ledger.lastSensorCount, sensorCount);

	return {
		...ledger,
		lastSensorCount: sensorCount,
		days: keepRecent({ ...ledger.days, [today]: (ledger.days[today] ?? 0) + delta }, today),
		synced: keepRecent(ledger.synced, today)
	};
}

export function recordedFrom(ledger: StepLedger, now: Date): number {
	const oldest = new Date(now.getFullYear(), now.getMonth(), now.getDate() - RECORDED_DAYS).getTime();
	const since = new Date(ledger.since ?? now.getTime());
	const sinceMidnight = new Date(since.getFullYear(), since.getMonth(), since.getDate()).getTime();

	return Math.max(oldest, sinceMidnight);
}

export function applyRecorded(ledger: StepLedger, buckets: RecordedBucket[]): StepLedger {
	const since = ledger.since;

	if (since === null) {
		return ledger;
	}

	const recorded: Record<string, number> = {};

	for (const bucket of buckets) {
		if (bucket.start >= since) {
			const day = localDay(new Date(bucket.start));
			recorded[day] = (recorded[day] ?? 0) + bucket.steps;
		}
	}

	const days = { ...ledger.days };

	for (const [day, steps] of Object.entries(recorded)) {
		days[day] = Math.max(days[day] ?? 0, steps);
	}

	return { ...ledger, days };
}

export function unsyncedSteps(ledger: StepLedger, day: string): number {
	return Math.max(0, (ledger.days[day] ?? 0) - (ledger.synced[day] ?? 0));
}

export function pendingEntries(ledger: StepLedger, today: string): StepEntry[] {
	const oldest = oldestKeptDay(today);

	return Object.keys(ledger.days)
		.sort()
		.filter((day) => day >= oldest && ledger.days[day] > (ledger.synced[day] ?? 0))
		.map((day) => ({ day, steps: ledger.days[day] }));
}

export function markSynced(ledger: StepLedger, entries: StepEntry[]): StepLedger {
	const synced = { ...ledger.synced };

	for (const entry of entries) {
		synced[entry.day] = Math.max(synced[entry.day] ?? 0, entry.steps);
	}

	return { ...ledger, synced };
}
