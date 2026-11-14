<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { FRIEND_SEARCH_FROM, filterFriends, friendGoalFill, friendRowLabel, sortFriendsBySteps } from "~/utils/friends";
import { formatCount } from "~/utils/labels";
import { PULL_THRESHOLD } from "~/utils/pullToRefresh";

type PlayerActionsTarget = { linkId: number; playerId: number; pseudo: string; isFriend: boolean };

const { friends, received, sent, loaded, load, accept, cheer, remove } = useFriends();
const { block } = useSafety();
const { ensureLoaded } = useCatalog();
const loading = ref(false);
const loadError = ref("");
const actionError = ref("");
const notice = ref("");
const busy = ref<string | null>(null);
const actionsFor = ref<PlayerActionsTarget | null>(null);
const reporting = ref<{ playerId: number; pseudo: string } | null>(null);
const showingQr = ref(false);
const addingFriend = ref(false);
const profileId = ref<number | null>(null);
const cheerError = ref("");
const search = ref("");
const content = ref<HTMLElement | null>(null);
const NOTICE_DURATION_MS = 4000;
let noticeTimer: ReturnType<typeof setTimeout> | null = null;

const AVATAR_COLORS = ["var(--ocean)", "var(--coral)", "var(--ocean-light)"];

function initialColor(playerId: number): string {
	return AVATAR_COLORS[playerId % AVATAR_COLORS.length] ?? "var(--ocean)";
}

function initial(pseudo: string): string {
	return pseudo.charAt(0).toUpperCase();
}

function clearMessages(): void {
	actionError.value = "";
	notice.value = "";
}

// Comme un message éphémère d'Android : il s'efface tout seul au bout de quelques secondes.
watch(notice, (message) => {
	if (noticeTimer !== null) {
		clearTimeout(noticeTimer);
		noticeTimer = null;
	}

	if (message !== "") {
		noticeTimer = setTimeout(() => {
			notice.value = "";
		}, NOTICE_DURATION_MS);
	}
});

onBeforeUnmount(() => {
	if (noticeTimer !== null) {
		clearTimeout(noticeTimer);
	}
});

function friendAdded(message: string): void {
	addingFriend.value = false;
	notice.value = message;
}

function showQrFromAdd(): void {
	addingFriend.value = false;
	showingQr.value = true;
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
const showSearch = computed(() => friends.value.length >= FRIEND_SEARCH_FROM);
const shownFriends = computed(() => (showSearch.value ? filterFriends(rankedFriends.value, search.value) : rankedFriends.value));
// Par identifiant : la fiche suit les rechargements de la liste, et se ferme si l'ami n'y est plus.
const profileFriendship = computed(() => friends.value.find((friendship) => friendship.id === profileId.value) ?? null);

watch(profileFriendship, (friendship) => {
	if (friendship === null) {
		profileId.value = null;
	}
});

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

// La fiche s'ouvre tout de suite avec la liste déjà chargée, puis se met à jour : les pas ont pu avancer depuis.
function openProfile(id: number): void {
	clearMessages();
	cheerError.value = "";
	profileId.value = id;
	load().catch(() => undefined);
}

async function cheerFromProfile(): Promise<void> {
	const friendship = profileFriendship.value;

	if (friendship === null || busy.value !== null) {
		return;
	}

	busy.value = `cheer-${friendship.id}`;
	cheerError.value = "";

	try {
		await cheer(friendship.id);
		notice.value = `Coup de patte envoyé à ${friendship.player.pseudo} !`;
	} catch (error) {
		cheerError.value = readableMessage(error);
	} finally {
		busy.value = null;
	}
}

function actionsFromProfile(): void {
	const friendship = profileFriendship.value;

	if (friendship) {
		profileId.value = null;
		openActions({ linkId: friendship.id, playerId: friendship.player.id, pseudo: friendship.player.pseudo, isFriend: true });
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

const { offset: pullOffset, refreshing: pulling } = usePullToRefresh(content, async () => {
	clearMessages();

	try {
		await load();
	} catch (error) {
		actionError.value = readableMessage(error);
	}
});

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<button class="avatar-btn" type="button" aria-label="Afficher mon QR code ami" @click="showingQr = true">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
					<rect x="3.5" y="3.5" width="6" height="6" rx="1"/>
					<rect x="14.5" y="3.5" width="6" height="6" rx="1"/>
					<rect x="3.5" y="14.5" width="6" height="6" rx="1"/>
					<path d="M14.5 14.5h2.5v2.5M20.5 14.5v0M14.5 20.5h2.5M20.5 17.5v3h-1"/>
				</svg>
			</button>
			<span class="topbar-title">Amis</span>
			<button class="avatar-btn" type="button" aria-label="Ajouter un ami" @click="addingFriend = true">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
			</button>
		</div>
		<div ref="content" class="content">
			<div
				class="pull-indicator"
				:class="{ ready: pullOffset >= PULL_THRESHOLD, spinning: pulling }"
				:style="{ height: `${pulling ? PULL_THRESHOLD : pullOffset}px` }"
				aria-hidden="true"
			>
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v4.5h-4.5"/></svg>
			</div>
			<p v-if="pulling" class="sr-only" role="status">Actualisation…</p>
			<p v-if="notice" class="toast" role="status">{{ notice }}</p>
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
				<input
					v-if="showSearch"
					v-model="search"
					class="friend-search"
					type="search"
					autocomplete="off"
					placeholder="Chercher un ami"
					aria-label="Chercher un ami par pseudo"
				>
				<div v-if="rankedFriends.length === 0" class="empty-state">
					<img class="empty-logo" src="/img/logo.png" alt="">
					<p class="gallery-note empty-note">
						Tu n'as pas encore d'ami. Scanne le QR d'un proche ou partage ton code pour suivre vos pas ensemble.
					</p>
					<button class="btn btn-primary" type="button" @click="addingFriend = true">Ajouter un ami</button>
				</div>
				<p v-else-if="shownFriends.length === 0" class="gallery-note empty-note">Aucun ami ne s'appelle comme ça.</p>
				<div v-for="friendship in shownFriends" :key="friendship.id" class="player-row">
					<div class="row-main">
						<button
							class="profile-btn"
							type="button"
							:aria-label="friendRowLabel(friendship)"
							:disabled="busy !== null"
							@click="openProfile(friendship.id)"
						>
							<span class="friend-avatar-box">
								<CompanionAvatar
									v-if="friendship.player.companion"
									:species="friendship.player.companion.species"
									:color="friendship.player.companion.color"
									:accessories="friendship.player.companion.accessories"
									:name="friendship.player.companion.name"
									:small="true"
								/>
								<img v-else class="friend-fallback" src="/img/logo.png" alt="">
							</span>
							<span class="identity">
								<span class="name-row">
									<span class="name">{{ friendship.player.pseudo }}</span>
									<StreakFlame v-if="friendship.player.streak" :streak="friendship.player.streak" />
									<PawIcon v-if="friendship.cheer.received && !friendship.cheer.sent" class="cheer-waiting" />
								</span>
								<span v-if="friendship.player.companion" class="sub">
									{{ friendship.player.companion.name }} · Niv. {{ friendship.player.companion.level }}
								</span>
							</span>
							<span v-if="friendship.player.today" class="meta">{{ formatCount(friendship.player.today.steps) }} pas</span>
							<svg v-else class="meta-masked" viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
								<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>
							</svg>
						</button>
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
						v-if="friendship.player.today"
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

			<NuxtLink v-if="loaded" class="link-under" to="/bloques">Joueurs bloqués ›</NuxtLink>

			<div v-if="loading && !loaded" class="card list-card" aria-busy="true" aria-label="Chargement de tes amis">
				<div v-for="row in 3" :key="row" class="player-row skeleton-row" aria-hidden="true">
					<span class="skeleton skeleton-avatar"></span>
					<span class="skeleton-lines">
						<span class="skeleton skeleton-line"></span>
						<span class="skeleton skeleton-line short"></span>
					</span>
				</div>
			</div>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
			</div>
		</div>
		<AddFriendSheet v-if="addingFriend" @close="addingFriend = false" @added="friendAdded" @show-qr="showQrFromAdd" />
		<FriendQrSheet v-if="showingQr" @close="closeQr" />
		<FriendProfileSheet
			v-if="profileFriendship"
			:friendship="profileFriendship"
			:cheering="busy === `cheer-${profileFriendship.id}`"
			:cheer-error="cheerError"
			@close="profileId = null"
			@cheer="cheerFromProfile"
			@actions="actionsFromProfile"
		/>
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

.profile-btn {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	gap: 14px;
	padding: 0;
	border: none;
	background: none;
	font-family: inherit;
	color: inherit;
	text-align: left;
	cursor: pointer;
}

.profile-btn:active:not(:disabled) {
	opacity: 0.6;
}

.profile-btn:disabled {
	cursor: default;
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

/* Message éphémère, fixé au-dessus de la barre du bas pour rester visible même après avoir fait défiler la liste. */
.toast {
	position: fixed;
	left: 50%;
	bottom: calc(96px + env(safe-area-inset-bottom, 0px));
	z-index: 40;
	width: max-content;
	max-width: calc(100% - 48px);
	margin: 0;
	padding: 12px 18px;
	border-radius: var(--radius-sm);
	background: var(--ink);
	color: var(--white);
	font-size: 14px;
	font-weight: 600;
	text-align: center;
	box-shadow: var(--shadow-float);
	transform: translateX(-50%);
}

.name-row .cheer-waiting {
	width: 16px;
	height: 16px;
	color: var(--coral);
}

.meta-masked {
	flex: 0 0 auto;
	width: 16px;
	height: 16px;
	stroke: var(--ocean-light);
}

.friend-search {
	margin: 4px 0 6px;
	padding: 10px 12px;
	border: 1px solid var(--mist);
	border-radius: var(--radius-sm);
	font-family: inherit;
	font-size: 14px;
	color: var(--ink);
}

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	padding: 8px 0 16px;
	text-align: center;
}

.empty-logo {
	width: 72px;
	height: 72px;
	object-fit: contain;
}

.empty-state .btn {
	width: auto;
	padding: 10px 20px;
}

/* Indicateur du « tirer pour rafraîchir » : il grandit avec le geste, puis tourne pendant le chargement. */
.pull-indicator {
	/* Compense le gap de .content : replié, l'indicateur ne décale pas la page. */
	margin-bottom: -18px;
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
	transition: height 0.15s ease-out;
}

.pull-indicator svg {
	width: 22px;
	height: 22px;
	stroke: var(--ocean-light);
	transition: transform 0.15s;
}

.pull-indicator.ready svg {
	stroke: var(--ocean);
	transform: rotate(180deg);
}

.pull-indicator.spinning svg {
	stroke: var(--ocean);
	animation: pull-spin 0.8s linear infinite;
}

@keyframes pull-spin {
	to {
		transform: rotate(360deg);
	}
}

.skeleton-row {
	flex-direction: row;
	align-items: center;
	gap: 14px;
}

.skeleton {
	display: block;
	background: var(--mist);
	animation: skeleton-pulse 1.2s ease-in-out infinite;
}

.skeleton-avatar {
	flex: 0 0 44px;
	height: 44px;
	border-radius: 50%;
}

.skeleton-lines {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.skeleton-line {
	height: 12px;
	width: 60%;
	border-radius: 6px;
}

.skeleton-line.short {
	width: 35%;
}

@keyframes skeleton-pulse {
	50% {
		opacity: 0.5;
	}
}

@media (prefers-reduced-motion: reduce) {
	.skeleton,
	.pull-indicator.spinning svg {
		animation: none;
	}
}

.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip: rect(0 0 0 0);
	white-space: nowrap;
}

.link-under {
	align-self: center;
	text-decoration: none;
}
</style>
