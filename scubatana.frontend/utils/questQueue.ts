export interface PendingAction {
	questId: number;
	taskId: number;
	body: Record<string, unknown>;
}

export function parseQueue(raw: string | null): PendingAction[] {
	if (raw === null) {
		return [];
	}

	try {
		const parsed = JSON.parse(raw) as unknown;

		return Array.isArray(parsed)
			? parsed.filter((entry): entry is PendingAction => typeof entry?.questId === "number" && typeof entry?.taskId === "number" && typeof entry?.body === "object" && entry.body !== null)
			: [];
	} catch {
		return [];
	}
}

export function withAction(queue: PendingAction[], action: PendingAction): PendingAction[] {
	return [...queue.filter((entry) => entry.questId !== action.questId || entry.taskId !== action.taskId), action];
}

export function withoutAction(queue: PendingAction[], action: PendingAction): PendingAction[] {
	return queue.filter((entry) => entry !== action);
}

export function isPending(queue: PendingAction[], questId: number, taskId: number): boolean {
	return queue.some((entry) => entry.questId === questId && entry.taskId === taskId);
}
