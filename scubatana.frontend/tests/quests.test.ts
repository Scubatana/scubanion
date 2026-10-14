import { describe, expect, it } from "vitest";
import { ApiError } from "~/utils/apiError";
import { actionMessage, currentQuestion, rewardLabel, statusLabel, taskFill } from "~/utils/quests";
import type { PlayerQuest, QuestTask } from "~/utils/types";

const accessories = [{ id: "crown", label: "Couronne", category: "hat", image: "a", earnable: true }];

function task(overrides: Partial<QuestTask> = {}): QuestTask {
	return { id: 1, type: "steps", action: "auto", description: "Marche", target: 1000, value: 250, completed: false, place: null, place_type: null, questions: null, rewards: [], ...overrides };
}

function quest(overrides: Partial<PlayerQuest> = {}): PlayerQuest {
	return { id: 1, quest_id: 1, name: "Quête", description: null, icon: null, period: "once", expires_at: null, status: "active", step: 2, steps: 3, tasks: [], step_rewards: [], quest_rewards: [], rewards_to_claim: [], ...overrides };
}

describe("rewardLabel", () => {
	it("adds up the experience and names the accessories from the catalog", () => {
		expect(rewardLabel([{ xp: 300, accessory: null }, { xp: 1200, accessory: "crown" }], accessories)).toBe("1 500 XP + Couronne");
	});

	it("falls back on the code of an accessory missing from the catalog", () => {
		expect(rewardLabel([{ xp: 0, accessory: "leaf_hat" }], accessories)).toBe("leaf_hat");
	});
});

describe("taskFill", () => {
	it("fills in proportion to the target, never beyond full", () => {
		expect(taskFill(task())).toBe("25%");
		expect(taskFill(task({ value: 5000 }))).toBe("100%");
		expect(taskFill(task({ target: 0 }))).toBe("0%");
	});
});

describe("currentQuestion", () => {
	it("gives the question matching the number of good answers", () => {
		const quiz = task({ action: "answer", value: 1, questions: [{ question: "A ?", choices: ["x"] }, { question: "B ?", choices: ["y"] }] });

		expect(currentQuestion(quiz)?.question).toBe("B ?");
		expect(currentQuestion(task())).toBeNull();
	});
});

describe("statusLabel", () => {
	it("shows the step of an active quest and the outcome of the others", () => {
		expect(statusLabel(quest())).toBe("Étape 2 sur 3");
		expect(statusLabel(quest({ status: "completed" }))).toBe("Terminée");
		expect(statusLabel(quest({ status: "failed" }))).toBe("Ratée");
		expect(statusLabel(quest({ status: "expired" }))).toBe("Expirée");
	});
});

describe("actionMessage", () => {
	it("prefers the message of the refused field, then the message of the error", () => {
		expect(actionMessage(new ApiError(422, "Certaines informations sont incorrectes.", { position: "Tu n'es pas encore sur place." }))).toBe("Tu n'es pas encore sur place.");
		expect(actionMessage(new ApiError(409, "Une erreur est survenue. Réessaie."))).toBe("Une erreur est survenue. Réessaie.");
		expect(actionMessage(new Error("Active la localisation pour valider ce lieu."))).toBe("Active la localisation pour valider ce lieu.");
	});
});
