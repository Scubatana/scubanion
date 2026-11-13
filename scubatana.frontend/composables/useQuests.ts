import { Preferences } from "@capacitor/preferences";
import { ApiError, isTransientError } from "~/utils/apiError";
import { isPending, parseQueue, withAction, withoutAction, type PendingAction } from "~/utils/questQueue";
import { actionMessage } from "~/utils/quests";
import type { ClaimableReward, Companion, PlayerQuest, QuestStatus } from "~/utils/types";

interface QuestsResponse {
	quests: PlayerQuest[];
}

interface ActResponse {
	status: QuestStatus;
	quest: PlayerQuest | null;
}

interface ClaimResponse {
	claimed: ClaimableReward[];
	quest: PlayerQuest | null;
	companion: Companion | null;
}

export type ActOutcome = QuestStatus | "queued";

const QUEUE_KEY_PREFIX = "quests.pending.";

let flushing = false;

export function useQuests() {
	const quests = useState<PlayerQuest[]>("quests", () => []);
	const pending = useState<PendingAction[]>("quests-pending", () => []);
	const pendingOwner = useState<number | null>("quests-pending-owner", () => null);
	const flushErrors = useState<Record<number, string>>("quests-flush-errors", () => ({}));
	const flushNotice = useState<string>("quests-flush-notice", () => "");

	function queueKey(): string | null {
		const playerId = usePlayer().player.value?.id;

		return playerId === undefined ? null : `${QUEUE_KEY_PREFIX}${playerId}`;
	}

	async function restorePending(): Promise<void> {
		const playerId = usePlayer().player.value?.id ?? null;
		const key = queueKey();

		if (key === null || pendingOwner.value === playerId) {
			return;
		}

		pending.value = parseQueue((await Preferences.get({ key })).value);
		pendingOwner.value = playerId;
	}

	async function savePending(queue: PendingAction[]): Promise<void> {
		pending.value = queue;
		const key = queueKey();

		if (key !== null) {
			await Preferences.set({ key, value: JSON.stringify(queue) });
		}
	}

	function replace(id: number, quest: PlayerQuest | null): void {
		quests.value = quest === null
			? quests.value.filter((entry) => entry.id !== id)
			: quests.value.map((entry) => (entry.id === id ? quest : entry));
	}

	async function send(action: PendingAction): Promise<QuestStatus> {
		const response = await useApi().post<ActResponse>(`/me/quests/${action.questId}/tasks/${action.taskId}`, action.body);

		replace(action.questId, response.quest);

		return response.status;
	}

	async function flush(): Promise<void> {
		await restorePending();

		if (flushing || pending.value.length === 0) {
			return;
		}

		flushing = true;

		try {
			for (const action of [...pending.value]) {
				try {
					await send(action);
					clearFlushError(action.taskId);
				} catch (error) {
					if (isTransientError(error)) {
						return;
					}

					if (error instanceof ApiError && error.status === 401) {
						reset();
						return;
					}

					const message = actionMessage(error);
					flushErrors.value = { ...flushErrors.value, [action.taskId]: message };
					flushNotice.value = `Validation hors ligne refusée : ${message}`;
				}

				await savePending(withoutAction(pending.value, action));
			}
		} finally {
			flushing = false;
		}
	}

	async function load(): Promise<void> {
		await useSteps().settled();
		await flush();
		quests.value = (await useApi().get<QuestsResponse>("/me/quests")).quests;
	}

	async function act(questId: number, taskId: number, body: Record<string, unknown>, queueable = false): Promise<ActOutcome> {
		const action = { questId, taskId, body: { recorded_at: new Date().toISOString(), ...body } };

		try {
			return await send(action);
		} catch (error) {
			if (!queueable || !isTransientError(error)) {
				throw error;
			}

			await restorePending();
			await savePending(withAction(pending.value, action));

			return "queued";
		}
	}

	async function claim(questId: number): Promise<ClaimableReward[]> {
		const response = await useApi().post<ClaimResponse>(`/me/quests/${questId}/claim`);

		replace(questId, response.quest);

		if (response.companion !== null) {
			usePlayer().companion.value = response.companion;
		}

		return response.claimed;
	}

	function waiting(questId: number, taskId: number): boolean {
		return isPending(pending.value, questId, taskId);
	}

	function clearFlushError(taskId?: number): void {
		if (taskId !== undefined && taskId in flushErrors.value) {
			const next = { ...flushErrors.value };
			delete next[taskId];
			flushErrors.value = next;
		} else if (taskId === undefined) {
			flushErrors.value = {};
		}

		if (taskId === undefined || Object.keys(flushErrors.value).length === 0) {
			flushNotice.value = "";
		}
	}

	function reset(): void {
		quests.value = [];
		pending.value = [];
		pendingOwner.value = null;
		flushErrors.value = {};
		flushNotice.value = "";
	}

	async function discard(): Promise<void> {
		const key = queueKey();
		reset();

		if (key !== null) {
			await Preferences.remove({ key });
		}
	}

	return { quests, pending, flushErrors, flushNotice, load, act, claim, flush, waiting, clearFlushError, reset, discard };
}
