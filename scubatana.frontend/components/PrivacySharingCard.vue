<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import type { PlayerPrivacy } from "~/utils/types";

const { player, savePrivacy } = usePlayer();
const saving = ref(false);
const error = ref("");

const privacy = computed<PlayerPrivacy>(() => player.value?.privacy ?? { share_steps: false, share_streak: false });

async function update(next: PlayerPrivacy): Promise<void> {
	if (saving.value || !player.value) {
		return;
	}

	saving.value = true;
	error.value = "";

	try {
		await savePrivacy(next);
	} catch (caught) {
		error.value = readableMessage(caught);
	} finally {
		saving.value = false;
	}
}

function toggleSteps(): void {
	void update({ ...privacy.value, share_steps: !privacy.value.share_steps });
}

function toggleStreak(): void {
	void update({ ...privacy.value, share_streak: !privacy.value.share_streak });
}
</script>

<template>
	<div v-if="player" class="card stack-tight">
		<span class="section-title">Partage avec mes amis</span>
		<p class="gallery-note">Par défaut, tes pas du jour et ta série sont masqués. Choisis ce que tes amis peuvent voir.</p>
		<div class="consent-row">
			<div class="consent-copy">
				<b>Pas du jour et objectif</b>
				<span class="gallery-note">Affiche ton compteur quotidien et ta jauge sur l'écran Amis.</span>
			</div>
			<button
				class="consent-switch"
				:class="{ active: privacy.share_steps }"
				type="button"
				role="switch"
				:aria-checked="privacy.share_steps"
				aria-label="Partager mes pas du jour avec mes amis"
				:disabled="saving"
				@click="toggleSteps"
			>
				<span></span>
			</button>
		</div>
		<div class="consent-row">
			<div class="consent-copy">
				<b>Série de jours</b>
				<span class="gallery-note">Affiche ta flamme de série à côté de ton pseudo.</span>
			</div>
			<button
				class="consent-switch"
				:class="{ active: privacy.share_streak }"
				type="button"
				role="switch"
				:aria-checked="privacy.share_streak"
				aria-label="Partager ma série avec mes amis"
				:disabled="saving"
				@click="toggleStreak"
			>
				<span></span>
			</button>
		</div>
		<p v-if="error" class="form-error">{{ error }}</p>
	</div>
</template>
