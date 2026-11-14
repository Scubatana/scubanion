<script setup lang="ts">
import { formatFriendSince, friendCompanionDetails, friendGoalFill, type Friendship } from "~/utils/friends";
import { formatCount } from "~/utils/labels";

const props = defineProps<{
	friendship: Friendship;
	cheering: boolean;
	cheerError: string;
}>();

const emit = defineEmits<{
	close: [];
	actions: [];
	cheer: [];
}>();

const { catalog } = useCatalog();
const closeButton = ref<HTMLButtonElement | null>(null);

const player = computed(() => props.friendship.player);
const details = computed(() => (player.value.companion ? friendCompanionDetails(catalog.value, player.value.companion) : null));
const since = computed(() => formatFriendSince(props.friendship.since));
const sharesEverything = computed(() => player.value.today !== null && player.value.streak !== null);

const cheerLabel = computed(() => {
	if (props.cheering) {
		return "Envoi…";
	}

	if (props.friendship.cheer.sent) {
		return "Coup de patte envoyé";
	}

	return props.friendship.cheer.received ? "Répondre d'un coup de patte" : "Coup de patte";
});

useSheet(() => emit("close"));

onMounted(() => closeButton.value?.focus());
</script>

<template>
	<Teleport to="body">
		<div class="sheet-backdrop" @click.self="emit('close')">
			<div class="card sheet" role="dialog" aria-modal="true" aria-labelledby="friend-profile-title">
				<span class="sheet-handle" aria-hidden="true"></span>
				<button ref="closeButton" class="avatar-btn sheet-close" type="button" aria-label="Fermer la fiche" @click="emit('close')">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>

				<div class="profile-stage">
					<CompanionAvatar
						v-if="player.companion"
						:species="player.companion.species"
						:color="player.companion.color"
						:accessories="player.companion.accessories"
						:name="player.companion.name"
					/>
					<img v-else class="profile-fallback" src="/img/logo.png" alt="">
				</div>

				<div class="profile-identity">
					<h2 id="friend-profile-title" class="profile-pseudo">{{ player.pseudo }}</h2>
					<span v-if="player.companion" class="profile-companion">
						{{ player.companion.name }}<template v-if="details?.species"> · {{ details.species }}</template>
					</span>
					<span v-else class="profile-companion">N'a pas encore choisi son compagnon</span>
					<span v-if="since" class="eyebrow">Amis depuis le {{ since }}</span>
				</div>

				<div class="stat-grid">
					<div class="stat-tile">
						<span class="stat-value">{{ player.companion ? player.companion.level : "–" }}</span>
						<span class="eyebrow">Niveau</span>
					</div>
					<div class="stat-tile" :class="{ masked: !player.today }">
						<template v-if="player.today">
							<span class="stat-value">{{ formatCount(player.today.steps) }}</span>
							<span class="eyebrow">pas sur {{ formatCount(player.today.goal) }}</span>
							<div
								class="wave-bar thin"
								:style="{ '--fill': friendGoalFill(player.today) }"
								role="img"
								:aria-label="`${formatCount(player.today.steps)} pas sur ${formatCount(player.today.goal)}`"
							><span></span></div>
						</template>
						<template v-else>
							<span class="stat-value stat-masked" aria-hidden="true">–</span>
							<span class="eyebrow">Pas masqués</span>
						</template>
					</div>
					<div class="stat-tile" :class="{ masked: !player.streak }">
						<template v-if="player.streak">
							<StreakFlame :streak="player.streak" />
							<span class="eyebrow">{{ player.streak.days <= 1 ? "jour de série" : "jours de série" }}</span>
						</template>
						<template v-else>
							<span class="stat-value stat-masked" aria-hidden="true">–</span>
							<span class="eyebrow">Série masquée</span>
						</template>
					</div>
				</div>
				<p v-if="!sharesEverything" class="gallery-note privacy-note">{{ player.pseudo }} a choisi de ne pas tout partager.</p>

				<div v-if="details && details.accessories.length > 0" class="showcase">
					<span class="section-title">Accessoires portés</span>
					<ul class="showcase-list">
						<li v-for="accessory in details.accessories" :key="accessory.id" class="showcase-item">
							<span class="showcase-thumb"><img :src="accessory.image" alt=""></span>
							<span class="showcase-label">{{ accessory.label }}</span>
						</li>
					</ul>
				</div>

				<p v-if="friendship.cheer.received" class="cheer-received" role="status">
					<PawIcon />
					{{ player.pseudo }} t'a envoyé un coup de patte aujourd'hui.
				</p>
				<p v-if="cheerError" class="form-error">{{ cheerError }}</p>
				<button
					class="btn btn-primary cheer-btn"
					type="button"
					:disabled="cheering || friendship.cheer.sent"
					@click="emit('cheer')"
				>
					<PawIcon />
					{{ cheerLabel }}
				</button>
				<p v-if="friendship.cheer.sent" class="gallery-note cheer-note">Tu pourras lui en envoyer un autre demain.</p>
				<button class="btn btn-ghost" type="button" @click="emit('actions')">Plus d'actions</button>
			</div>
		</div>
	</Teleport>
</template>

<style scoped>
.sheet {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 14px;
	padding-top: 10px;
	animation: sheet-up 0.22s ease-out;
}

@keyframes sheet-up {
	from {
		transform: translateY(24px);
		opacity: 0;
	}
}

@media (prefers-reduced-motion: reduce) {
	.sheet {
		animation: none;
	}
}

.sheet-handle {
	align-self: center;
	width: 36px;
	height: 4px;
	border-radius: 2px;
	background: var(--mist);
}

.sheet-close {
	position: absolute;
	top: 12px;
	right: 12px;
	z-index: 1;
}

.profile-stage {
	display: flex;
	justify-content: center;
	padding-bottom: 8px;
	border-radius: var(--radius-sm);
	background: var(--paper);
}

/* Même proportion que l'avatar de l'accueil (170 px, 44 px de marge pour les chapeaux). */
.profile-stage :deep(.companion-avatar) {
	width: 130px;
	margin-top: 34px;
}

.profile-fallback {
	width: 80px;
	height: 80px;
	margin: 24px 0 16px;
	object-fit: contain;
}

.profile-identity {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 2px;
	text-align: center;
}

.profile-pseudo {
	margin: 0;
	font-family: 'Baloo 2', sans-serif;
	font-size: 24px;
	line-height: 1.2;
	color: var(--ink);
	overflow-wrap: anywhere;
}

.profile-companion {
	font-size: 14px;
	color: var(--ink-soft);
}

.stat-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 8px;
}

.stat-tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 4px;
	min-width: 0;
	padding: 12px 8px;
	border: 1.5px solid var(--mist);
	border-radius: var(--radius-sm);
	text-align: center;
}

.stat-tile.masked {
	border-style: dashed;
}

.stat-tile .wave-bar {
	width: 100%;
	margin-top: 2px;
}

.stat-value {
	font-family: 'Baloo 2', sans-serif;
	font-size: 22px;
	font-weight: 700;
	line-height: 1;
	color: var(--ocean-dark);
}

.stat-masked {
	color: var(--ocean-light);
}

.privacy-note {
	max-width: none;
	margin-top: -6px;
	text-align: center;
}

.cheer-received {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	margin: 0;
	padding: 10px 12px;
	border-radius: var(--radius-sm);
	background: var(--mist);
	font-size: 14px;
	font-weight: 600;
	color: var(--ocean-dark);
	text-align: center;
}

.cheer-btn {
	gap: 8px;
}

.cheer-note {
	max-width: none;
	margin-top: -8px;
	text-align: center;
	font-size: 13px;
}

.showcase {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.showcase-list {
	display: flex;
	gap: 8px;
	margin: 0;
	padding: 0;
	overflow-x: auto;
	list-style: none;
}

.showcase-item {
	flex: 0 0 72px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}

.showcase-thumb {
	width: 56px;
	height: 56px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: var(--radius-sm);
	background: var(--mist);
}

.showcase-thumb img {
	max-width: 40px;
	max-height: 40px;
	object-fit: contain;
}

.showcase-label {
	width: 100%;
	font-size: 11px;
	text-align: center;
	color: var(--ink-soft);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
