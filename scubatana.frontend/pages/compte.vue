<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { AMBITION_LABELS, formatCount } from "~/utils/labels";

const { player, load } = usePlayer();
const auth = useAuth();
const loadError = ref("");
const loggingOut = ref(false);

async function refresh(): Promise<void> {
	loadError.value = "";

	try {
		await load();
	} catch (error) {
		loadError.value = readableMessage(error);
	}
}

async function logout(): Promise<void> {
	if (loggingOut.value) {
		return;
	}

	loggingOut.value = true;
	await auth.logout();
	await navigateTo("/login", { replace: true });
}

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<NuxtLink class="avatar-btn" to="/" aria-label="Retour">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg>
			</NuxtLink>
			<span class="topbar-title">Ton compte</span>
			<NuxtLink class="avatar-btn" to="/parametres" aria-label="Paramètres">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
			</NuxtLink>
		</div>
		<div class="content">
			<div v-if="player" class="pet-stage">
				<img class="pet-img small" src="/img/logo.png" :alt="player.pseudo ?? 'Scubanion'">
				<h1 class="companion-name">{{ player.pseudo }}</h1>
				<span v-if="player.ambition" class="pill">{{ AMBITION_LABELS[player.ambition] }}</span>
				<span class="eyebrow">{{ player.email }}</span>
			</div>
			<div v-if="player" class="card stat-row">
				<div class="stat"><b>{{ formatCount(player.stats.total_steps) }}</b><span>pas cumulés</span></div>
				<div class="stat"><b>{{ formatCount(player.stats.quests_done) }}</b><span>quêtes finies</span></div>
				<div class="stat"><b>{{ formatCount(player.stats.friends_count) }}</b><span>amis</span></div>
			</div>
			<p v-if="loadError" class="form-error">{{ loadError }}</p>
			<button class="btn btn-ghost" type="button" :disabled="loggingOut" @click="logout">Se déconnecter</button>
		</div>
		<BottomNav />
	</div>
</template>
