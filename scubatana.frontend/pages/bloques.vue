<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { formatDay } from "~/utils/labels";

const { blocked, loaded, load, unblock } = useSafety();
const loading = ref(false);
const loadError = ref("");
const actionError = ref("");
const notice = ref("");
const unblocking = ref<number | null>(null);

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

async function unblockPlayer(id: number, pseudo: string): Promise<void> {
	if (unblocking.value !== null) {
		return;
	}

	unblocking.value = id;
	actionError.value = "";
	notice.value = "";

	try {
		await unblock(id);
		notice.value = `${pseudo} est débloqué. Pour redevenir amis, il faudra une nouvelle invitation.`;
	} catch (error) {
		actionError.value = readableMessage(error);
	} finally {
		unblocking.value = null;
	}
}

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<NuxtLink class="avatar-btn" to="/amis" aria-label="Retour aux amis">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg>
			</NuxtLink>
			<span class="topbar-title">Joueurs bloqués</span>
			<span class="topbar-spacer"></span>
		</div>
		<div class="content">
			<div class="card stack-tight">
				<p class="gallery-note">
					Un joueur bloqué ne peut plus t'ajouter en ami, et il n'en est pas prévenu.
					Le débloquer ne vous rend pas amis : il faudra une nouvelle invitation.
				</p>
				<p v-if="loaded && blocked.length === 0" class="gallery-note">Tu n'as bloqué personne.</p>
				<div v-for="entry in blocked" :key="entry.id" class="blocked-row">
					<div>
						<b>{{ entry.player.pseudo }}</b>
						<span class="eyebrow blocked-since">Bloqué le {{ formatDay(entry.since) }}</span>
					</div>
					<button
						class="btn btn-ghost compact-btn"
						type="button"
						:disabled="unblocking !== null"
						@click="unblockPlayer(entry.id, entry.player.pseudo)"
					>
						{{ unblocking === entry.id ? "…" : "Débloquer" }}
					</button>
				</div>
			</div>

			<p v-if="notice" class="gallery-note note-self-centered">{{ notice }}</p>
			<p v-if="actionError" class="form-error">{{ actionError }}</p>
			<p v-if="loading && !loaded" class="gallery-note note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>

<style scoped>
.blocked-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding-top: 12px;
	border-top: 1px solid var(--mist);
}

.blocked-since {
	display: block;
}

.compact-btn {
	width: auto;
	padding: 8px 14px;
	font-size: 13px;
}
</style>
