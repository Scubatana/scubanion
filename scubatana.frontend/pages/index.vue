<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";

const { companion, today, streak, load } = usePlayer();
const steps = useSteps();
const loading = ref(false);
const loadError = ref("");

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

const shownToday = computed(() => (today.value ? { ...today.value, steps: steps.displayedSteps.value } : null));

onMounted(async () => {
	await refresh();
	await steps.start(true).catch(() => undefined);
});
</script>

<template>
	<div class="page">
		<div class="topbar">
			<div class="brand">
				<img src="/img/logo.png" alt="Scubanion">
				<span>Scubanion</span>
			</div>
			<NuxtLink class="avatar-btn" to="/compte" aria-label="Ton compte">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.9 3.1-6 7-6s7 2.1 7 6"/></svg>
			</NuxtLink>
		</div>
		<div class="content">
			<CompanionCard v-if="companion" :companion="companion" />
			<NuxtLink v-if="shownToday" class="card-link" to="/pas" aria-label="Voir l'historique de tes pas">
				<StepGoal :today="shownToday" :streak="streak" />
			</NuxtLink>
			<div v-if="steps.status.value === 'denied'" class="card stack-tight">
				<p class="gallery-note">Autorise l'accès à l'activité physique pour que tes pas soient comptés. Si rien ne s'affiche, active-la dans les réglages.</p>
				<button class="btn btn-primary" type="button" @click="steps.start(true)">Autoriser le compteur de pas</button>
				<button class="btn btn-ghost" type="button" @click="steps.openSettings()">Ouvrir les réglages</button>
			</div>
			<p v-if="steps.status.value === 'unavailable'" class="gallery-note note-self-centered">Le compteur de pas n'est pas disponible sur cet appareil.</p>
			<p v-if="loading && !companion" class="gallery-note note-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
