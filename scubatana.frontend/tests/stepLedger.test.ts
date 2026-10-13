import { describe, expect, it } from "vitest";
import { applyReading, applyRecorded, emptyLedger, markSynced, parseLedger, pendingEntries, recordedFrom, resume, suspend, unsyncedSteps } from "~/utils/stepLedger";

const TODAY = "2026-10-13";

function at(day: string, hour: number): number {
	const [year, month, date] = day.split("-").map(Number);

	return new Date(year, month - 1, date, hour).getTime();
}

describe("applyReading", () => {
	it("counts nothing on the very first reading, which only sets the starting point", () => {
		const ledger = applyReading(emptyLedger(), 48210, TODAY);

		expect(ledger.days[TODAY]).toBe(0);
		expect(ledger.lastSensorCount).toBe(48210);
	});

	it("adds the steps walked since the previous reading", () => {
		let ledger = applyReading(emptyLedger(), 48210, TODAY);
		ledger = applyReading(ledger, 48300, TODAY);
		ledger = applyReading(ledger, 49500, TODAY);

		expect(ledger.days[TODAY]).toBe(1290);
	});

	it("keeps counting after a phone restart, when the sensor starts again from zero", () => {
		let ledger = applyReading(emptyLedger(), 48210, TODAY);
		ledger = applyReading(ledger, 48310, TODAY);
		ledger = applyReading(ledger, 35, TODAY);

		expect(ledger.days[TODAY]).toBe(135);
	});

	it("forgets days older than a month", () => {
		let ledger = applyReading(emptyLedger(), 100, "2026-09-01");
		ledger = applyReading(ledger, 300, "2026-09-01");
		ledger = applyReading(ledger, 500, TODAY);

		expect(Object.keys(ledger.days)).toEqual([TODAY]);
	});
});

describe("applyRecorded", () => {
	const tracking = resume(emptyLedger(), at("2026-10-10", 8));

	it("gives each day the steps walked while the app was closed", () => {
		const ledger = applyRecorded(tracking, [
			{ start: at("2026-10-11", 9), steps: 4000 },
			{ start: at("2026-10-11", 18), steps: 2500 },
			{ start: at("2026-10-12", 12), steps: 3100 },
			{ start: at(TODAY, 7), steps: 600 }
		]);

		expect(ledger.days).toEqual({ "2026-10-11": 6500, "2026-10-12": 3100, [TODAY]: 600 });
	});

	it("recovers the steps lost by a phone restart instead of the smaller live count", () => {
		let ledger = applyReading(applyReading(tracking, 9000, TODAY), 9200, TODAY);
		ledger = applyReading(ledger, 40, TODAY);

		expect(ledger.days[TODAY]).toBe(240);
		expect(applyRecorded(ledger, [{ start: at(TODAY, 9), steps: 1900 }]).days[TODAY]).toBe(1900);
	});

	it("never counts the same steps twice when the live count is already ahead", () => {
		const ledger = applyReading(applyReading(tracking, 100, TODAY), 900, TODAY);

		expect(applyRecorded(ledger, [{ start: at(TODAY, 9), steps: 750 }]).days[TODAY]).toBe(800);
	});

	it("ignores what was walked before this player started tracking on this phone", () => {
		const ledger = applyRecorded(tracking, [
			{ start: at("2026-10-09", 15), steps: 9000 },
			{ start: at("2026-10-10", 7), steps: 500 },
			{ start: at("2026-10-10", 9), steps: 300 }
		]);

		expect(ledger.days).toEqual({ "2026-10-10": 300 });
	});

	it("asks for at most nine days, starting at midnight of the day tracking began", () => {
		expect(recordedFrom(tracking, new Date(at(TODAY, 15)))).toBe(at("2026-10-10", 0));
		expect(recordedFrom(resume(emptyLedger(), at("2026-09-01", 8)), new Date(at(TODAY, 15)))).toBe(at("2026-10-04", 0));
	});
});

describe("changing player", () => {
	it("keeps the steps not yet sent when a player logs out, and sends them on return", () => {
		let ledger = applyReading(applyReading(resume(emptyLedger(), at(TODAY, 8)), 100, TODAY), 400, TODAY);
		ledger = resume(suspend(ledger), at(TODAY, 14));

		expect(pendingEntries(ledger, TODAY)).toEqual([{ day: TODAY, steps: 300 }]);
	});

	it("never gives a returning player the steps walked while logged out", () => {
		let ledger = applyReading(applyReading(resume(emptyLedger(), at(TODAY, 8)), 100, TODAY), 400, TODAY);
		ledger = resume(suspend(ledger), at(TODAY, 14));
		ledger = applyReading(ledger, 2400, TODAY);
		ledger = applyRecorded(ledger, [{ start: at(TODAY, 11), steps: 2000 }]);

		expect(ledger.days[TODAY]).toBe(300);
		expect(applyReading(ledger, 2450, TODAY).days[TODAY]).toBe(350);
	});
});

describe("syncing", () => {
	it("lists the days whose total has not reached the server yet, oldest first", () => {
		let ledger = applyReading(emptyLedger(), 1000, "2026-10-12");
		ledger = applyReading(ledger, 1800, "2026-10-12");
		ledger = applyReading(ledger, 2000, TODAY);

		expect(pendingEntries(ledger, TODAY)).toEqual([
			{ day: "2026-10-12", steps: 800 },
			{ day: TODAY, steps: 200 }
		]);
		expect(unsyncedSteps(ledger, TODAY)).toBe(200);
	});

	it("stops listing a day once it is synced, until new steps arrive", () => {
		let ledger = applyReading(applyReading(emptyLedger(), 0, TODAY), 200, TODAY);
		ledger = markSynced(ledger, pendingEntries(ledger, TODAY));

		expect(pendingEntries(ledger, TODAY)).toEqual([]);
		expect(unsyncedSteps(ledger, TODAY)).toBe(0);

		ledger = applyReading(ledger, 260, TODAY);

		expect(pendingEntries(ledger, TODAY)).toEqual([{ day: TODAY, steps: 260 }]);
		expect(unsyncedSteps(ledger, TODAY)).toBe(60);
	});

	it("never sends a day the server would refuse for being too old", () => {
		const ledger = { ...emptyLedger(), days: { "2026-08-20": 900, [TODAY]: 50 } };

		expect(pendingEntries(ledger, TODAY)).toEqual([{ day: TODAY, steps: 50 }]);
	});
});

describe("parseLedger", () => {
	it("starts empty when nothing or something unreadable is stored", () => {
		expect(parseLedger(null)).toEqual(emptyLedger());
		expect(parseLedger("pas du json")).toEqual(emptyLedger());
		expect(parseLedger("null")).toEqual(emptyLedger());
	});

	it("reads back what was stored", () => {
		const ledger = applyReading(applyReading(resume(emptyLedger(), 1000), 10, TODAY), 60, TODAY);

		expect(parseLedger(JSON.stringify(ledger))).toEqual(ledger);
	});
});
