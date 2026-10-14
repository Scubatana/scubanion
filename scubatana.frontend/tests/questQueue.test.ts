import { describe, expect, it } from "vitest";
import { isPending, parseQueue, withAction, withoutAction } from "~/utils/questQueue";

const scan = { questId: 4, taskId: 9, body: { code: "X" } };

describe("questQueue", () => {
	it("reads back a stored queue and ignores anything unreadable", () => {
		expect(parseQueue(JSON.stringify([scan]))).toEqual([scan]);
		expect(parseQueue(null)).toEqual([]);
		expect(parseQueue("pas du json")).toEqual([]);
		expect(parseQueue(JSON.stringify([{ questId: "4" }, scan]))).toEqual([scan]);
	});

	it("keeps only the latest attempt for a task", () => {
		const retried = { ...scan, body: { code: "Y" } };

		expect(withAction(withAction([], scan), retried)).toEqual([retried]);
	});

	it("removes a sent action and tells whether a task is waiting", () => {
		const queue = withAction([], scan);

		expect(isPending(queue, 4, 9)).toBe(true);
		expect(isPending(withoutAction(queue, scan), 4, 9)).toBe(false);
	});
});
