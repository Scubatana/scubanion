<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { formatCount } from "~/utils/labels";
import { GOAL_MAX, GOAL_MIN, GOAL_STEP, shiftedGoal } from "~/utils/stepGoal";

const { today, load, saveGoal } = usePlayer();
const loading = ref(false);
const saving = ref(false);
const loadError = ref("");
const saveError = ref("");

async function refresh(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	loadError.value = "";

	try {
		await load();
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		loading.value = false;
	}
}

async function changeGoal(offset: number): Promise<void> {
	const goal = today.value ? shiftedGoal(today.value.goal, offset) : null;

	if (saving.value || goal === null) {
		return;
	}

	saving.value = true;
	saveError.value = "";

	try {
		await saveGoal(goal);
	} catch (error) {
		saveError.value = readableMessage(error);
	} finally {
		saving.value = false;
	}
}

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<NuxtLink class="avatar-btn" to="/compte" aria-label="Retour">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg>
			</NuxtLink>
			<span class="topbar-title">Paramètres</span>
			<span class="topbar-spacer"></span>
		</div>
		<div class="content">
			<div v-if="today" class="card stack-tight">
				<span class="section-title">Objectif de pas par jour</span>
				<div class="row-center">
					<button class="avatar-btn goal-btn" type="button" aria-label="Baisser l'objectif" :disabled="saving || today.goal <= GOAL_MIN" @click="changeGoal(-GOAL_STEP)">
						<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 12h12"/></svg>
					</button>
					<div class="hero-steps">
						<svg class="goal-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle class="solid" cx="12" cy="12" r="1.6"/></svg>
						<span class="goal-value">{{ formatCount(today.goal) }}</span>
					</div>
					<button class="avatar-btn goal-btn" type="button" aria-label="Augmenter l'objectif" :disabled="saving || today.goal >= GOAL_MAX" @click="changeGoal(GOAL_STEP)">
						<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 12h12M12 6v12"/></svg>
					</button>
				</div>
				<p v-if="saveError" class="form-error">{{ saveError }}</p>
			</div>
			<p v-if="loading && !today" class="gallery-note note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
