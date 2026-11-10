<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";
import { formatFriendCode, friendGoalFill, sortFriendsBySteps } from "~/utils/friends";
import { formatCount } from "~/utils/labels";
import { QrScanError, scanQrCode } from "~/utils/qrScanner";

type PlayerActionsTarget = { linkId: number; playerId: number; pseudo: string; isFriend: boolean };

const { code, friends, received, sent, loaded, load, invite, scanQr, accept, remove } = useFriends();
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
const actionsFor = ref<PlayerActionsTarget | null>(null);
const reporting = ref<{ playerId: number; pseudo: string } | null>(null);
const showingQr = ref(false);
const isDev = import.meta.dev;
const devToken = ref("");

const AVATAR_COLORS = ["var(--ocean)", "var(--coral)", "var(--ocean-light)"];

function initialColor(playerId: number): string {
	return AVATAR_COLORS[playerId % AVATAR_COLORS.length] ?? "var(--ocean)";
}

function initial(pseudo: string): string {
	return pseudo.charAt(0).toUpperCase();
}

function clearMessages(): void {
	inviteError.value = "";
	actionError.value = "";
	notice.value = "";
}

async function scanFriendQr(): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	clearMessages();

	let token: string | null;

	try {
		token = await scanQrCode();
	} catch (error) {
		inviteError.value = error instanceof QrScanError ? error.message : readableMessage(error);
		return;
	}

	if (token !== null) {
		await addFriendByQr(token);
	}
}

async function addFriendByQr(token: string): Promise<void> {
	if (busy.value !== null || token.trim() === "") {
		return;
	}

	busy.value = "scan";
	clearMessages();

	try {
		const pseudo = await scanQr(token);
		devToken.value = "";
		notice.value = pseudo ? `${pseudo} fait maintenant partie de tes amis !` : "Vous êtes maintenant amis !";
	} catch (error) {
		inviteError.value = error instanceof ApiError && error.fields.token ? error.fields.token : readableMessage(error);
	} finally {
		busy.value = null;
	}
}

async function closeQr(): Promise<void> {
	showingQr.value = false;

	try {
		await load();
	} catch (error) {
		actionError.value = readableMessage(error);
	}
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
	clearMessages();

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
	clearMessages();

	try {
		await accept(id);
		notice.value = `${pseudo} fait maintenant partie de tes amis.`;
	} catch (error) {
		actionError.value = readableMessage(error);
	} finally {
		busy.value = null;
	}
}

async function deleteLink(id: number, message: string): Promise<boolean> {
	if (busy.value !== null) {
		return false;
	}

	busy.value = `remove-${id}`;
	clearMessages();

	try {
		await remove(id);
		notice.value = message;
		return true;
	} catch (error) {
		actionError.value = readableMessage(error);
		return false;
	} finally {
		busy.value = null;
	}
}

async function blockPlayer(playerId: number, pseudo: string): Promise<boolean> {
	if (busy.value !== null) {
		return false;
	}

	busy.value = `block-${playerId}`;
	clearMessages();

	try {
		await block(playerId);
		notice.value = `${pseudo} est bloqué. Vous ne pourrez plus vous ajouter en ami.`;
		await load();
		return true;
	} catch (error) {
		actionError.value = readableMessage(error);
		return false;
	} finally {
		busy.value = null;
	}
}

function openActions(target: PlayerActionsTarget): void {
	clearMessages();
	actionsFor.value = target;
}

function closeActions(): void {
	actionsFor.value = null;
	actionError.value = "";
}

async function removeFromActions(): Promise<void> {
	const target = actionsFor.value;

	if (target && (await deleteLink(target.linkId, `${target.pseudo} a été retiré de tes amis.`))) {
		actionsFor.value = null;
	}
}

async function blockFromActions(): Promise<void> {
	const target = actionsFor.value;

	if (target && (await blockPlayer(target.playerId, target.pseudo))) {
		actionsFor.value = null;
	}
}

function reportFromActions(): void {
	const target = actionsFor.value;

	if (target) {
		actionsFor.value = null;
		reporting.value = { playerId: target.playerId, pseudo: target.pseudo };
	}
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
			<button class="avatar-btn" type="button" aria-label="Afficher mon QR code ami" @click="showingQr = true">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
					<rect x="3.5" y="3.5" width="6" height="6" rx="1"/>
					<rect x="14.5" y="3.5" width="6" height="6" rx="1"/>
					<rect x="3.5" y="14.5" width="6" height="6" rx="1"/>
					<path d="M14.5 14.5h2.5v2.5M20.5 14.5v0M14.5 20.5h2.5M20.5 17.5v3h-1"/>
				</svg>
			</button>
		</div>
		<div class="content">
			<p v-if="notice" class="gallery-note quest-notice note-self-centered" role="status">{{ notice }}</p>
			<p v-if="actionError && !actionsFor" class="form-error">{{ actionError }}</p>

			<div v-if="received.length > 0" class="card list-card">
				<div class="row-between list-head">
					<span class="section-title">Invitations reçues</span>
					<span class="pill">{{ received.length }}</span>
				</div>
				<div v-for="invitation in received" :key="invitation.id" class="player-row invitation-row">
					<div class="row-main">
						<div class="avatar" :style="{ background: initialColor(invitation.player.id) }" aria-hidden="true">
							{{ initial(invitation.player.pseudo) }}
						</div>
						<div class="identity">
							<span class="name">{{ invitation.player.pseudo }}</span>
							<span class="sub">veut t'ajouter</span>
						</div>
						<button
							class="more-btn"
							type="button"
							:aria-label="`Plus d'actions pour ${invitation.player.pseudo}`"
							:disabled="busy !== null"
							@click="openActions({ linkId: invitation.id, playerId: invitation.player.id, pseudo: invitation.player.pseudo, isFriend: false })"
						>
							<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
						</button>
					</div>
					<div class="invite-actions">
						<button
							class="btn btn-primary compact-btn"
							type="button"
							:disabled="busy !== null"
							@click="acceptInvitation(invitation.id, invitation.player.pseudo)"
						>
							{{ busy === `accept-${invitation.id}` ? "…" : "Accepter" }}
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
				</div>
			</div>

			<div v-if="loaded" class="card list-card">
				<div class="row-between list-head">
					<span class="section-title">Mes amis</span>
					<span class="eyebrow">pas du jour</span>
				</div>
				<p v-if="rankedFriends.length === 0" class="gallery-note empty-note">
					Tu n'as pas encore d'ami. Scanne le QR d'un proche ou saisis son code pour suivre vos pas ensemble.
				</p>
				<div v-for="friendship in rankedFriends" :key="friendship.id" class="player-row">
					<div class="row-main">
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
						<div class="identity">
							<div class="name-row">
								<span class="name">{{ friendship.player.pseudo }}</span>
								<StreakFlame :streak="friendship.player.streak" />
							</div>
							<span v-if="friendship.player.companion" class="sub">
								{{ friendship.player.companion.name }} · Niv. {{ friendship.player.companion.level }}
							</span>
						</div>
						<span class="meta">{{ formatCount(friendship.player.today.steps) }} pas</span>
						<button
							class="more-btn"
							type="button"
							:aria-label="`Plus d'actions pour ${friendship.player.pseudo}`"
							:disabled="busy !== null"
							@click="openActions({ linkId: friendship.id, playerId: friendship.player.id, pseudo: friendship.player.pseudo, isFriend: true })"
						>
							<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
						</button>
					</div>
					<div
						class="wave-bar goal-bar"
						:style="{ '--fill': friendGoalFill(friendship.player.today) }"
						role="img"
						:aria-label="`${formatCount(friendship.player.today.steps)} pas sur ${formatCount(friendship.player.today.goal)}`"
					><span></span></div>
				</div>
			</div>

			<div v-if="sent.length > 0" class="card list-card">
				<span class="section-title list-head">Invitations envoyées</span>
				<div v-for="invitation in sent" :key="invitation.id" class="player-row">
					<div class="row-main">
						<div class="avatar" :style="{ background: initialColor(invitation.player.id) }" aria-hidden="true">
							{{ initial(invitation.player.pseudo) }}
						</div>
						<div class="identity">
							<span class="name">{{ invitation.player.pseudo }}</span>
							<span class="sub">En attente de réponse</span>
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
			</div>

			<form class="card stack-tight" novalidate @submit.prevent="submitInvite">
				<span class="section-title">Ajouter un ami</span>
				<div class="field">
					<label for="friend-code-input">Avec son code ami</label>
					<div class="invite-box">
						<input
							id="friend-code-input"
							v-model="inviteCode"
							type="text"
							autocomplete="off"
							maxlength="16"
							placeholder="Ex : K7Q2-MZ4P"
						>
						<button class="btn btn-primary" type="submit" :disabled="busy !== null || inviteCode.trim().length === 0">
							{{ busy === "invite" ? "…" : "Inviter" }}
						</button>
					</div>
					<span v-if="inviteError" class="field-error">{{ inviteError }}</span>
				</div>
				<button class="btn btn-ghost scan-btn" type="button" :disabled="busy !== null" @click="scanFriendQr">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
						<path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16M4 12h16"/>
					</svg>
					{{ busy === "scan" ? "Ajout…" : "Scanner le QR d'un ami" }}
				</button>
				<p v-if="code" class="own-code">
					Ton code : <b>{{ formattedCode }}</b>
					<button class="inline-action" type="button" @click="copyCode">{{ copied ? "Copié" : "Copier" }}</button>
				</p>
				<div v-if="isDev" class="field dev-field">
					<label for="dev-qr-token">Dev : coller un jeton QR (sans caméra)</label>
					<div class="invite-box">
						<input id="dev-qr-token" v-model="devToken" type="text" autocomplete="off" placeholder="eyJpdiI6...">
						<button class="btn btn-ghost" type="button" :disabled="busy !== null || devToken.trim() === ''" @click="addFriendByQr(devToken)">
							Ajouter
						</button>
					</div>
				</div>
			</form>

			<NuxtLink v-if="loaded" class="link-under" to="/bloques">Joueurs bloqués ›</NuxtLink>

			<p v-if="loading && !loaded" class="gallery-note note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<FriendQrSheet v-if="showingQr" @close="closeQr" />
		<PlayerActionsSheet
			v-if="actionsFor"
			:pseudo="actionsFor.pseudo"
			:can-remove="actionsFor.isFriend"
			:busy="busy !== null"
			:error="actionError"
			@close="closeActions"
			@remove="removeFromActions"
			@block="blockFromActions"
			@report="reportFromActions"
		/>
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
.list-card {
	display: flex;
	flex-direction: column;
	padding: 14px 20px 6px;
}

.list-head {
	padding-bottom: 6px;
}

.player-row {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px 0;
	border-bottom: 1px solid var(--mist);
}

.player-row:last-child {
	border-bottom: none;
}

.row-main {
	display: flex;
	align-items: center;
	gap: 14px;
}

.identity {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.name-row {
	display: flex;
	align-items: center;
	gap: 6px;
}

.name {
	font-weight: 600;
	font-size: 15px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.sub {
	font-size: 12px;
	color: var(--ink-soft);
}

.meta {
	font-size: 12px;
	font-weight: 600;
	color: var(--coral-dark);
	white-space: nowrap;
}

.friend-avatar-box {
	flex: 0 0 44px;
	width: 44px;
	height: 44px;
	border-radius: 50%;
	background: var(--mist);
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}

.friend-avatar-box :deep(.companion-avatar.small) {
	width: 40px;
	margin-top: 6px;
}

.friend-fallback {
	width: 30px;
	height: 30px;
	object-fit: contain;
}

.goal-bar {
	height: 6px;
	margin-left: 58px;
}

.more-btn {
	flex: 0 0 auto;
	width: 40px;
	height: 40px;
	margin-right: -10px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: none;
	border-radius: 50%;
	background: none;
	color: var(--ink-soft);
	cursor: pointer;
}

.more-btn svg {
	width: 20px;
	height: 20px;
	fill: currentColor;
}

.more-btn:disabled {
	opacity: 0.5;
	cursor: default;
}

.invite-actions {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 8px;
	margin-left: 58px;
}

.compact-btn {
	width: auto;
	padding: 8px 14px;
	font-size: 14px;
}

.empty-note {
	max-width: none;
	padding: 4px 0 12px;
}

.invite-box input {
	min-width: 0;
	width: 100%;
}

.invite-box .btn {
	flex: 0 0 auto;
	box-shadow: none;
}

.scan-btn {
	gap: 8px;
}

.scan-btn svg {
	width: 18px;
	height: 18px;
	stroke: currentColor;
}

.own-code {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	font-size: 13px;
	color: var(--ink-soft);
}

.own-code b {
	font-family: 'Baloo 2', sans-serif;
	letter-spacing: 1px;
	color: var(--ocean-dark);
}

.inline-action {
	padding: 8px 6px;
	border: none;
	background: none;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: var(--ocean);
	cursor: pointer;
}

.dev-field {
	padding: 10px;
	border: 1px dashed var(--ocean-light);
	border-radius: var(--radius-sm);
}

.link-under {
	align-self: center;
	text-decoration: none;
}
</style>
