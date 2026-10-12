<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { formatCount } from "~/utils/labels";

const { companion, today, load } = usePlayer();
const loading = ref(false);
const loadError = ref("");

const xpFill = computed(() => (companion.value ? `${Math.min(100, Math.round((companion.value.xp / companion.value.xp_for_next_level) * 100))}%` : "0%"));

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

onMounted(refresh);
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
			<div v-if="companion" class="pet-stage">
				<div class="bubble">Hey, on sort ? 🌊</div>
				<img class="pet-img" src="/img/logo.png" :alt="companion.name">
				<h1 class="companion-name">{{ companion.name }}</h1>
				<span class="pill">Niveau {{ companion.level }}</span>
				<div class="xp-row">
					<div class="wave-bar thin" :style="{ '--fill': xpFill }"><span></span></div>
					<span class="eyebrow">{{ formatCount(companion.xp) }} / {{ formatCount(companion.xp_for_next_level) }} XP</span>
				</div>
			</div>
			<StepGoal v-if="today" :today="today" />
			<p v-if="loading && !companion" class="gallery-note note-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
