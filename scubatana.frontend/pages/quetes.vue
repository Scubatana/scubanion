<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { currentPosition } from "~/utils/geolocation";
import { formatCount } from "~/utils/labels";
import { actionMessage, currentQuestion, rewardLabel, statusLabel, taskFill } from "~/utils/quests";
import type { PlayerQuest, QuestTask } from "~/utils/types";

const { quests, load, act, claim, waiting } = useQuests();
const { catalog, ensureLoaded } = useCatalog();
const { today } = usePlayer();
const loading = ref(false);
const loadError = ref("");
const busy = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const notice = ref("");
const codes = ref<Record<number, string>>({});

const accessories = computed(() => catalog.value?.accessories ?? []);

async function refresh(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	loadError.value = "";

	try {
		await Promise.all([ensureLoaded(), load()]);
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		loading.value = false;
	}
}

async function run(key: string, work: () => Promise<string | void>): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	busy.value = key;
	notice.value = "";
	errors.value = { ...errors.value, [key]: "" };

	try {
		notice.value = (await work()) ?? "";
	} catch (error) {
		errors.value = { ...errors.value, [key]: actionMessage(error) };
	} finally {
		busy.value = null;
	}
}

function queuedNotice(outcome: string): string {
	return outcome === "queued" ? "Pas de réseau : ta validation sera envoyée dès son retour." : "";
}

function checkIn(quest: PlayerQuest, task: QuestTask): void {
	void run(`task-${task.id}`, async () => {
		const outcome = await act(quest.id, task.id, { ...(await currentPosition(true)) }, true);
		void usePosition().start();

		return queuedNotice(outcome);
	});
}

function scan(quest: PlayerQuest, task: QuestTask): void {
	void run(`task-${task.id}`, async () => queuedNotice(await act(quest.id, task.id, { code: codes.value[task.id] ?? "" }, true)));
}

function answer(quest: PlayerQuest, task: QuestTask, choice: number): void {
	void run(`task-${task.id}`, async () => {
		const status = await act(quest.id, task.id, { question: task.value, choice });

		return status === "failed" ? `Mauvaise réponse : la quête « ${quest.name} » est ratée.` : "";
	});
}

function collect(quest: PlayerQuest): void {
	void run(`quest-${quest.id}`, async () => `Tu as gagné ${rewardLabel(await claim(quest.id), accessories.value)} !`);
}

watch(() => today.value?.steps, () => {
	void load().catch(() => undefined);
});

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<span class="topbar-spacer"></span>
			<span class="topbar-title">Quêtes</span>
			<span class="topbar-spacer"></span>
		</div>
		<div class="content">
			<p v-if="notice" class="card quest-notice">{{ notice }}</p>
			<div v-for="quest in quests" :key="quest.id" class="card stack-tight">
				<div class="row-between">
					<span class="section-title">{{ quest.name }}</span>
					<span class="pill">{{ statusLabel(quest) }}</span>
				</div>
				<p v-if="quest.description" class="gallery-note">{{ quest.description }}</p>
				<div v-for="task in quest.tasks" :key="task.id" class="quest-task stack-tight">
					<div class="row-between">
						<span>{{ task.description }}</span>
						<span class="eyebrow">{{ formatCount(task.value) }} / {{ formatCount(task.target) }}</span>
					</div>
					<div class="wave-bar thin" :style="{ '--fill': taskFill(task) }"><span></span></div>
					<span v-if="task.rewards.length > 0" class="eyebrow">Récompense : {{ rewardLabel(task.rewards, accessories) }}</span>
					<span v-if="waiting(quest.id, task.id)" class="eyebrow">En attente de réseau</span>
					<template v-else-if="!task.completed">
						<button v-if="task.action === 'checkin'" class="btn btn-ghost" type="button" :disabled="busy !== null" @click="checkIn(quest, task)">{{ busy === `task-${task.id}` ? "Localisation…" : "Je suis sur place" }}</button>
						<template v-if="task.action === 'scan'">
							<div class="field">
								<input v-model="codes[task.id]" type="text" autocomplete="off" placeholder="Code">
							</div>
							<button class="btn btn-ghost" type="button" :disabled="busy !== null" @click="scan(quest, task)">Valider le code</button>
						</template>
						<template v-if="task.action === 'answer' && currentQuestion(task)">
							<span class="section-title">{{ currentQuestion(task)?.question }}</span>
							<button v-for="(choice, index) in currentQuestion(task)?.choices" :key="index" class="btn btn-ghost" type="button" :disabled="busy !== null" @click="answer(quest, task, index)">{{ choice }}</button>
						</template>
					</template>
					<p v-if="errors[`task-${task.id}`]" class="form-error">{{ errors[`task-${task.id}`] }}</p>
				</div>
				<span v-if="quest.step_rewards.length > 0" class="eyebrow">Fin de l'étape : {{ rewardLabel(quest.step_rewards, accessories) }}</span>
				<span v-if="quest.quest_rewards.length > 0" class="eyebrow">Fin de la quête : {{ rewardLabel(quest.quest_rewards, accessories) }}</span>
				<button v-if="quest.rewards_to_claim.length > 0" class="btn btn-primary" type="button" :disabled="busy !== null" @click="collect(quest)">Récupérer : {{ rewardLabel(quest.rewards_to_claim, accessories) }}</button>
				<p v-if="errors[`quest-${quest.id}`]" class="form-error">{{ errors[`quest-${quest.id}`] }}</p>
			</div>
			<p v-if="!loading && !loadError && quests.length === 0" class="gallery-note note-self-centered">Aucune quête pour le moment.</p>
			<p v-if="loading && quests.length === 0" class="gallery-note note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
