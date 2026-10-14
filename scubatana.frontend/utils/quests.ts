import { ApiError, readableMessage } from "./apiError";
import { formatCount } from "./labels";
import type { CatalogAccessory, PlayerQuest, QuestQuestion, QuestReward, QuestTask } from "./types";

export function rewardLabel(rewards: QuestReward[], accessories: CatalogAccessory[]): string {
	const xp = rewards.reduce((total, reward) => total + reward.xp, 0);
	const items = rewards
		.map((reward) => reward.accessory)
		.filter((code): code is string => code !== null)
		.map((code) => accessories.find((accessory) => accessory.id === code)?.label ?? code);

	return [...(xp > 0 ? [`${formatCount(xp)} XP`] : []), ...items].join(" + ");
}

export function taskFill(task: QuestTask): string {
	return task.target > 0 ? `${Math.min(100, Math.round((task.value / task.target) * 100))}%` : "0%";
}

export function currentQuestion(task: QuestTask): QuestQuestion | null {
	return task.questions?.[task.value] ?? null;
}

export function statusLabel(quest: PlayerQuest): string {
	const labels: Record<PlayerQuest["status"], string> = {
		active: `Étape ${quest.step} sur ${quest.steps}`,
		completed: "Terminée",
		failed: "Ratée",
		expired: "Expirée"
	};

	return labels[quest.status];
}

export function actionMessage(error: unknown): string {
	if (error instanceof ApiError) {
		return Object.values(error.fields)[0] ?? error.message;
	}

	return error instanceof Error && error.message !== "" ? error.message : readableMessage(error);
}
