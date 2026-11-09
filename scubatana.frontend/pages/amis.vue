<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";
import { formatFriendCode, friendGoalFill, sortFriendsBySteps } from "~/utils/friends";
import { formatCount } from "~/utils/labels";

const { code, friends, received, sent, loaded, load, invite, accept, remove } = useFriends();
const { block } = useSafety();
const { ensureLoaded } = useCatalog();
const loading = ref(false);
const loadError = ref("");
const inviteCode = ref("");
const inviteError = ref("");
const actionError = ref("");
const notice = ref("");
const copied = ref(false);
const busy = ref<string | null>(null);
const confirming = ref<{ key: string; action: "remove" | "block" } | null>(null);
const reporting = ref<{ playerId: number; pseudo: string } | null>(null);

function isConfirming(key: string, action: "remove" | "block"): boolean {
	return confirming.value?.key === key && confirming.value.action === action;
}

const rankedFriends = computed(() => sortFriendsBySteps(friends.value));
const formattedCode = computed(() => formatFriendCode(code.value));

async function refresh(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	loadError.value = "";

	try {
		await Promise.all([ensureLoaded().catch(() => undefined), load()]);
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		loading.value = false;
	}
}

async function copyCode(): Promise<void> {
	if (!code.value || typeof navigator === "undefined" || !navigator.clipboard) {
		return;
	}

	try {
		await navigator.clipboard.writeText(code.value);
		copied.value = true;
		setTimeout(() => {
			copied.value = false;
		}, 2000);
	} catch {
		copied.value = false;
	}
}

async function submitInvite(): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	busy.value = "invite";
	inviteError.value = "";
	actionError.value = "";
	notice.value = "";

	try {
		const outcome = await invite(inviteCode.value);
		inviteCode.value = "";
		notice.value = outcome === "mutual" ? "Invitation réciproque : vous êtes maintenant amis !" : "Invitation envoyée.";
	} catch (error) {
		inviteError.value = error instanceof ApiError && error.fields.code ? error.fields.code : readableMessage(error);
	} finally {
		busy.value = null;
	}
}

async function acceptInvitation(id: number, pseudo: string): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	busy.value = `accept-${id}`;
	actionError.value = "";
	notice.value = "";

	try {
		await accept(id);
		notice.value = `${pseudo} fait maintenant partie de tes amis.`;
	} catch (error) {
		actionError.value = readableMessage(error);
	} finally {
		busy.value = null;
	}
}

async function deleteLink(id: number, message: string): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	busy.value = `remove-${id}`;
	actionError.value = "";
	notice.value = "";

	try {
		await remove(id);
		confirming.value = null;
		notice.value = message;
	} catch (error) {
		actionError.value = readableMessage(error);
	} finally {
		busy.value = null;
	}
}

async function blockPlayer(playerId: number, pseudo: string): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	busy.value = `block-${playerId}`;
	actionError.value = "";
	notice.value = "";

	try {
		await block(playerId);
		confirming.value = null;
		notice.value = `${pseudo} est bloqué. Vous ne pourrez plus vous ajouter en ami.`;
		await load();
	} catch (error) {
		actionError.value = readableMessage(error);
	} finally {
		busy.value = null;
	}
}

function openReport(playerId: number, pseudo: string): void {
	confirming.value = null;
	actionError.value = "";
	notice.value = "";
	reporting.value = { playerId, pseudo };
}

async function reportDone(result: { message: string; blocked: boolean }): Promise<void> {
	reporting.value = null;
	notice.value = result.message;

	if (result.blocked) {
		try {
			await load();
		} catch (error) {
			actionError.value = readableMessage(error);
		}
	}
}

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<span class="topbar-spacer"></span>
			<span class="topbar-title">Amis</span>
			<span class="topbar-spacer"></span>
		</div>
		<div class="content">
			<div v-if="code" class="card stack-tight">
				<span class="section-title">Ton code ami</span>
				<p class="gallery-note">Partage ce code pour qu'un ami t'ajoute sur Scubanion.</p>
				<div class="code-banner">
					<b class="friend-code">{{ formattedCode }}</b>
					<button class="btn btn-ghost copy-btn" type="button" @click="copyCode">{{ copied ? "Copié !" : "Copier" }}</button>
				</div>
			</div>

			<form class="card stack-tight" novalidate @submit.prevent="submitInvite">
				<span class="section-title">Ajouter un ami</span>
				<div class="field">
					<label for="friend-code-input">Code à 8 caractères</label>
					<input
						id="friend-code-input"
						v-model="inviteCode"
						type="text"
						autocomplete="off"
						maxlength="16"
						placeholder="Ex : K7Q2-MZ4P"
					>
					<span v-if="inviteError" class="field-error">{{ inviteError }}</span>
				</div>
				<button class="btn btn-primary" type="submit" :disabled="busy !== null || inviteCode.trim().length === 0">
					{{ busy === "invite" ? "Envoi…" : "Envoyer une invitation" }}
				</button>
			</form>

			<p v-if="notice" class="gallery-note quest-notice note-self-centered">{{ notice }}</p>
			<p v-if="actionError" class="form-error">{{ actionError }}</p>

			<div v-if="received.length > 0" class="card stack-tight">
				<div class="row-between">
					<span class="section-title">Invitations reçues</span>
					<span class="pill">{{ received.length }}</span>
				</div>
				<div v-for="invitation in received" :key="invitation.id" class="invite-item">
					<div class="invite-head">
						<b>{{ invitation.player.pseudo }}</b>
						<span class="eyebrow">veut t'ajouter</span>
					</div>
					<div class="invite-actions">
						<button
							class="btn btn-primary compact-btn"
							type="button"
							:disabled="busy !== null"
							@click="acceptInvitation(invitation.id, invitation.player.pseudo)"
						>
							Accepter
						</button>
						<button
							class="btn btn-ghost compact-btn"
							type="button"
							:disabled="busy !== null"
							@click="deleteLink(invitation.id, 'Invitation refusée.')"
						>
							Refuser
						</button>
					</div>
					<div class="safety-actions">
						<template v-if="isConfirming(`invitation-${invitation.id}`, 'block')">
							<span class="eyebrow">Bloquer {{ invitation.player.pseudo }} ?</span>
							<button
								class="inline-action danger"
								type="button"
								:disabled="busy !== null"
								@click="blockPlayer(invitation.player.id, invitation.player.pseudo)"
							>
								Bloquer
							</button>
							<button class="inline-action" type="button" :disabled="busy !== null" @click="confirming = null">Annuler</button>
						</template>
						<template v-else>
							<button
								class="inline-action"
								type="button"
								:disabled="busy !== null"
								@click="confirming = { key: `invitation-${invitation.id}`, action: 'block' }"
							>
								Bloquer
							</button>
							<button class="inline-action" type="button" :disabled="busy !== null" @click="openReport(invitation.player.id, invitation.player.pseudo)">
								Signaler
							</button>
						</template>
					</div>
				</div>
			</div>

			<div v-if="loaded" class="card stack-tight">
				<div class="row-between">
					<span class="section-title">Pas du jour entre amis</span>
					<span class="eyebrow">{{ rankedFriends.length }} {{ rankedFriends.length > 1 ? "amis" : "ami" }}</span>
				</div>
				<p v-if="rankedFriends.length === 0" class="gallery-note">
					Tu n'as pas encore d'ami ajouté. Partage ton code ou saisis celui d'un proche pour suivre vos pas quotidiens.
				</p>
				<div v-for="friendship in rankedFriends" :key="friendship.id" class="friend-item">
					<div class="friend-top">
						<div class="friend-avatar-box">
							<CompanionAvatar
								v-if="friendship.player.companion"
								:species="friendship.player.companion.species"
								:color="friendship.player.companion.color"
								:accessories="friendship.player.companion.accessories"
								:name="friendship.player.companion.name"
								:small="true"
							/>
							<img v-else class="friend-fallback" src="/img/logo.png" alt="">
						</div>
						<div class="friend-identity">
							<div class="friend-title-row">
								<b>{{ friendship.player.pseudo }}</b>
								<StreakFlame :streak="friendship.player.streak" />
							</div>
							<span v-if="friendship.player.companion" class="eyebrow">
								{{ friendship.player.companion.name }} · Niv. {{ friendship.player.companion.level }}
							</span>
						</div>
					</div>
					<div class="xp-row">
						<div class="wave-bar thin" :style="{ '--fill': friendGoalFill(friendship.player.today) }"><span></span></div>
						<span class="eyebrow">{{ formatCount(friendship.player.today.steps) }} / {{ formatCount(friendship.player.today.goal) }} pas</span>
					</div>
					<div class="safety-actions">
						<template v-if="isConfirming(`friend-${friendship.id}`, 'remove')">
							<span class="eyebrow">Retirer {{ friendship.player.pseudo }} ?</span>
							<button
								class="inline-action danger"
								type="button"
								:disabled="busy !== null"
								@click="deleteLink(friendship.id, `${friendship.player.pseudo} a été retiré de tes amis.`)"
							>
								Retirer
							</button>
							<button class="inline-action" type="button" :disabled="busy !== null" @click="confirming = null">Annuler</button>
						</template>
						<template v-else-if="isConfirming(`friend-${friendship.id}`, 'block')">
							<span class="eyebrow">Bloquer {{ friendship.player.pseudo }} ?</span>
							<button
								class="inline-action danger"
								type="button"
								:disabled="busy !== null"
								@click="blockPlayer(friendship.player.id, friendship.player.pseudo)"
							>
								Bloquer
							</button>
							<button class="inline-action" type="button" :disabled="busy !== null" @click="confirming = null">Annuler</button>
						</template>
						<template v-else>
							<button
								class="inline-action"
								type="button"
								:disabled="busy !== null"
								@click="confirming = { key: `friend-${friendship.id}`, action: 'remove' }"
							>
								Retirer
							</button>
							<button
								class="inline-action"
								type="button"
								:disabled="busy !== null"
								@click="confirming = { key: `friend-${friendship.id}`, action: 'block' }"
							>
								Bloquer
							</button>
							<button class="inline-action" type="button" :disabled="busy !== null" @click="openReport(friendship.player.id, friendship.player.pseudo)">
								Signaler
							</button>
						</template>
					</div>
				</div>
			</div>

			<div v-if="sent.length > 0" class="card stack-tight">
				<span class="section-title">Invitations envoyées</span>
				<div v-for="invitation in sent" :key="invitation.id" class="invite-item invite-row">
					<div>
						<b>{{ invitation.player.pseudo }}</b>
						<span class="eyebrow block-eyebrow">En attente de réponse</span>
					</div>
					<button
						class="btn btn-ghost compact-btn"
						type="button"
						:disabled="busy !== null"
						@click="deleteLink(invitation.id, 'Invitation annulée.')"
					>
						Annuler
					</button>
				</div>
			</div>

			<NuxtLink v-if="loaded" class="blocked-link" to="/bloques">
				Joueurs bloqués
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
			</NuxtLink>

			<p v-if="loading && !loaded" class="gallery-note note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<ReportSheet
			v-if="reporting"
			:player-id="reporting.playerId"
			:pseudo="reporting.pseudo"
			@close="reporting = null"
			@done="reportDone"
		/>
		<BottomNav />
	</div>
</template>

<style scoped>
.code-banner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 10px 14px;
	border-radius: var(--radius-sm);
	background: var(--mist);
}

.friend-code {
	font-family: 'Baloo 2', sans-serif;
	font-size: 22px;
	letter-spacing: 2px;
	color: var(--ocean-dark);
}

.copy-btn {
	width: auto;
	padding: 8px 14px;
	font-size: 13px;
}

.invite-item,
.friend-item {
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding-top: 12px;
	border-top: 1px solid var(--mist);
}

.invite-row {
	flex-direction: row;
	align-items: center;
	justify-content: space-between;
}

.invite-head {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 8px;
}

.invite-actions {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 8px;
}

.compact-btn {
	width: auto;
	padding: 8px 14px;
	font-size: 13px;
}

.friend-top {
	display: flex;
	align-items: center;
	gap: 12px;
}

.friend-avatar-box {
	flex: 0 0 54px;
	width: 54px;
	height: 54px;
	border-radius: 50%;
	background: var(--mist);
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}

.friend-avatar-box :deep(.companion-avatar.small) {
	width: 48px;
	margin-top: 6px;
}

.friend-fallback {
	width: 36px;
	height: 36px;
	object-fit: contain;
}

.friend-identity {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.friend-title-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.block-eyebrow {
	display: block;
}

.safety-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 14px;
}

.safety-actions .eyebrow {
	margin-right: auto;
}

.blocked-link {
	align-self: center;
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 13px;
	font-weight: 600;
	color: var(--ink-soft);
	text-decoration: none;
}

.blocked-link svg {
	width: 14px;
	height: 14px;
	stroke: currentColor;
}

.inline-action {
	background: none;
	border: none;
	padding: 0;
	font-family: inherit;
	font-size: 12px;
	font-weight: 600;
	color: var(--ink-soft);
	cursor: pointer;
}

.inline-action.danger {
	color: var(--coral-dark);
}

.inline-action:disabled {
	opacity: 0.5;
	cursor: default;
}
</style>
