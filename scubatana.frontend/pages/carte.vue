<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import {
	filterMapEntries,
	formatDistance,
	projectOutline,
	projectPoint,
	ZOOM_SPANS,
	type MapFilter,
	type NearbyMapEntry
} from "~/utils/map";

const MAP_WIDTH = 320;
const MAP_HEIGHT = 240;

const {
	bbox,
	playerLocation,
	zoomIndex,
	referencePoint,
	placeTypes,
	places,
	terminals,
	truncated,
	loaded,
	load,
	recenterOnPlayer,
	zoomIn,
	zoomOut,
	pan,
	focusOn
} = useMap();

const loading = ref(false);
const moving = ref(false);
const loadError = ref("");
const locationNotice = ref("");
const searchQuery = ref("");
const activeFilter = ref<MapFilter>("all");
const selectedKey = ref<string | null>(null);

const entries = computed(() =>
	filterMapEntries(places.value, terminals.value, placeTypes.value, referencePoint.value, searchQuery.value, activeFilter.value)
);

const selectedEntry = computed<NearbyMapEntry | null>(() =>
	entries.value.find((entry) => entry.key === selectedKey.value) ?? null
);

const visibleOutlines = computed(() => {
	if (activeFilter.value === "terminals") {
		return [];
	}

	const typesByCode = new Map(placeTypes.value.map((entry) => [entry.code, entry]));

	return places.value
		.filter((place) => place.outline && place.outline.length >= 3 && (activeFilter.value === "all" || place.type === activeFilter.value))
		.map((place) => ({
			id: place.id,
			points: projectOutline(place.outline!, bbox.value, MAP_WIDTH, MAP_HEIGHT),
			color: typesByCode.get(place.type)?.color ?? "#2D8FA5"
		}));
});

const projectedEntries = computed(() =>
	entries.value
		.map((entry) => ({
			entry,
			point: projectPoint(entry.coordinates, bbox.value, MAP_WIDTH, MAP_HEIGHT)
		}))
		.filter(({ point }) => point.inside)
);

const projectedPlayer = computed(() =>
	playerLocation.value ? projectPoint(playerLocation.value, bbox.value, MAP_WIDTH, MAP_HEIGHT) : null
);

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

async function moveMap(work: () => Promise<void>): Promise<void> {
	if (moving.value) {
		return;
	}

	moving.value = true;
	locationNotice.value = "";

	try {
		await work();
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		moving.value = false;
	}
}

async function locateMe(): Promise<void> {
	if (moving.value) {
		return;
	}

	moving.value = true;
	locationNotice.value = "";

	try {
		const found = await recenterOnPlayer();

		if (!found) {
			locationNotice.value = "Position indisponible : affichage centré sur la zone par défaut.";
		}
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		moving.value = false;
	}
}

async function selectAndFocus(entry: NearbyMapEntry): Promise<void> {
	selectedKey.value = entry.key;
	await moveMap(() => focusOn(entry.coordinates));
}

onMounted(refresh);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<span class="topbar-spacer"></span>
			<span class="topbar-title">Carte</span>
			<button class="avatar-btn locate-btn" type="button" aria-label="Centrer sur ma position" :disabled="moving" @click="locateMe">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">
					<circle cx="12" cy="12" r="4"/>
					<path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>
				</svg>
			</button>
		</div>

		<div class="content">
			<div class="card stack-tight">
				<div class="field">
					<label for="map-search">Rechercher à proximité</label>
					<input
						id="map-search"
						v-model="searchQuery"
						type="search"
						autocomplete="off"
						placeholder="Parc, lieu partenaire, borne…"
					>
				</div>
				<div class="filter-chips" role="tablist" aria-label="Filtrer la carte">
					<button
						class="chip"
						:class="{ active: activeFilter === 'all' }"
						type="button"
						@click="activeFilter = 'all'"
					>
						Tout
					</button>
					<button
						class="chip"
						:class="{ active: activeFilter === 'terminals' }"
						type="button"
						@click="activeFilter = 'terminals'"
					>
						<span class="chip-dot terminal-dot"></span>
						Bornes
					</button>
					<button
						v-for="placeType in placeTypes"
						:key="placeType.code"
						class="chip"
						:class="{ active: activeFilter === placeType.code }"
						type="button"
						@click="activeFilter = placeType.code"
					>
						<span class="chip-dot" :style="{ backgroundColor: placeType.color }"></span>
						{{ placeType.label }}
					</button>
				</div>
			</div>

			<div class="card map-card stack-tight">
				<div class="map-viewport">
					<svg class="map-svg" :viewBox="`0 0 ${MAP_WIDTH} ${MAP_HEIGHT}`" role="img" aria-label="Carte des lieux et bornes">
						<defs>
							<pattern id="map-grid" width="32" height="32" patternUnits="userSpaceOnUse">
								<path d="M 32 0 L 0 0 0 32" fill="none" stroke="#DCECEF" stroke-width="0.8"/>
							</pattern>
						</defs>
						<rect :width="MAP_WIDTH" :height="MAP_HEIGHT" fill="#EEF7F8"/>
						<rect :width="MAP_WIDTH" :height="MAP_HEIGHT" fill="url(#map-grid)"/>
						<circle :cx="MAP_WIDTH / 2" :cy="MAP_HEIGHT / 2" r="52" fill="none" stroke="#CFE5E9" stroke-dasharray="4 4"/>
						<circle :cx="MAP_WIDTH / 2" :cy="MAP_HEIGHT / 2" r="96" fill="none" stroke="#CFE5E9" stroke-dasharray="4 4"/>

						<polygon
							v-for="outline in visibleOutlines"
							:key="`outline-${outline.id}`"
							:points="outline.points"
							:fill="outline.color"
							fill-opacity="0.16"
							:stroke="outline.color"
							stroke-width="1.5"
						/>

						<g
							v-for="{ entry, point } in projectedEntries"
							:key="entry.key"
							class="map-marker"
							:class="{ selected: selectedKey === entry.key }"
							role="button"
							tabindex="0"
							:aria-label="entry.name"
							@click="selectedKey = entry.key"
							@keydown.enter.prevent="selectedKey = entry.key"
						>
							<template v-if="entry.kind === 'terminal'">
								<circle :cx="point.x" :cy="point.y" r="11" :fill="entry.online ? '#1E8A96' : '#9EB1B7'" fill-opacity="0.22"/>
								<rect
									:x="point.x - 6"
									:y="point.y - 6"
									width="12"
									height="12"
									rx="3"
									:fill="entry.online ? '#1E8A96' : '#7E949B'"
									stroke="#FFFFFF"
									stroke-width="2"
								/>
							</template>
							<template v-else>
								<circle
									:cx="point.x"
									:cy="point.y"
									:r="selectedKey === entry.key ? 8 : 6.5"
									:fill="entry.color"
									stroke="#FFFFFF"
									stroke-width="2"
								/>
							</template>
						</g>

						<g v-if="projectedPlayer && projectedPlayer.inside" class="player-pin" aria-label="Ta position">
							<circle :cx="projectedPlayer.x" :cy="projectedPlayer.y" r="11" fill="#F07153" fill-opacity="0.25"/>
							<circle :cx="projectedPlayer.x" :cy="projectedPlayer.y" r="5.5" fill="#F07153" stroke="#FFFFFF" stroke-width="2"/>
						</g>
					</svg>

					<div class="map-controls">
						<button class="map-ctrl-btn" type="button" aria-label="Zoomer" :disabled="moving || zoomIndex <= 0" @click="moveMap(zoomIn)">+</button>
						<button class="map-ctrl-btn" type="button" aria-label="Dézoomer" :disabled="moving || zoomIndex >= ZOOM_SPANS.length - 1" @click="moveMap(zoomOut)">-</button>
					</div>

					<div class="map-pan">
						<button class="map-pan-btn" type="button" aria-label="Déplacer au nord" :disabled="moving" @click="moveMap(() => pan(1, 0))">▲</button>
						<div class="map-pan-mid">
							<button class="map-pan-btn" type="button" aria-label="Déplacer à l'ouest" :disabled="moving" @click="moveMap(() => pan(0, -1))">◀</button>
							<button class="map-pan-btn" type="button" aria-label="Déplacer à l'est" :disabled="moving" @click="moveMap(() => pan(0, 1))">▶</button>
						</div>
						<button class="map-pan-btn" type="button" aria-label="Déplacer au sud" :disabled="moving" @click="moveMap(() => pan(-1, 0))">▼</button>
					</div>
				</div>

				<div class="map-legend">
					<span class="legend-item"><i class="legend-dot player-dot"></i> Toi</span>
					<span class="legend-item"><i class="legend-square"></i> Borne en ligne</span>
					<span class="legend-item"><i class="legend-dot place-dot"></i> Lieu</span>
				</div>
				<p v-if="truncated" class="gallery-note">Zoome davantage pour afficher tous les lieux présents dans cette zone.</p>
				<p v-if="locationNotice" class="gallery-note">{{ locationNotice }}</p>
			</div>

			<div v-if="selectedEntry" class="card stack-tight selected-card">
				<div class="row-between">
					<div>
						<b>{{ selectedEntry.name }}</b>
						<span class="eyebrow block-eyebrow">{{ selectedEntry.subtitle }}</span>
					</div>
					<span class="pill">{{ formatDistance(selectedEntry.distanceMeters) }}</span>
				</div>
				<div v-if="selectedEntry.kind === 'terminal'" class="row-between">
					<span class="status-badge" :class="{ online: selectedEntry.online }">
						{{ selectedEntry.online ? "Borne en ligne" : "Borne hors ligne" }}
					</span>
					<span class="eyebrow">Approche ton téléphone du repère NFC</span>
				</div>
				<span v-else-if="selectedEntry.radiusMeters" class="eyebrow">Rayon de validation : {{ selectedEntry.radiusMeters }} m</span>
			</div>

			<div v-if="loaded" class="card stack-tight">
				<div class="row-between">
					<span class="section-title">À proximité</span>
					<span class="eyebrow">{{ entries.length }} {{ entries.length > 1 ? "résultats" : "résultat" }}</span>
				</div>
				<p v-if="entries.length === 0" class="gallery-note">
					Aucun lieu ni aucune borne ne correspond à ta recherche dans cette zone. Essaie de dézoomer ou de changer de filtre.
				</p>
				<button
					v-for="entry in entries"
					:key="`list-${entry.key}`"
					class="nearby-row"
					:class="{ active: selectedKey === entry.key }"
					type="button"
					@click="selectAndFocus(entry)"
				>
					<span
						class="nearby-badge"
						:class="{ square: entry.kind === 'terminal' }"
						:style="{ backgroundColor: entry.color }"
					></span>
					<div class="nearby-copy">
						<b>{{ entry.name }}</b>
						<span class="eyebrow">
							{{ entry.subtitle }}
							<template v-if="entry.kind === 'terminal'">
								· {{ entry.online ? "En ligne" : "Hors ligne" }}
							</template>
						</span>
					</div>
					<span class="nearby-distance">{{ formatDistance(entry.distanceMeters) }}</span>
				</button>
			</div>

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
.locate-btn {
	cursor: pointer;
}

.filter-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 6px 12px;
	border-radius: 999px;
	border: 1.5px solid var(--mist);
	background: var(--white);
	color: var(--ink-soft);
	font-family: inherit;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
}

.chip.active {
	border-color: var(--ocean);
	background: var(--mist);
	color: var(--ocean-dark);
}

.chip-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}

.terminal-dot {
	border-radius: 2px;
	background: var(--ocean);
}

.map-card {
	padding: 12px;
}

.map-viewport {
	position: relative;
	width: 100%;
	border-radius: var(--radius-sm);
	overflow: hidden;
	border: 1px solid var(--mist);
}

.map-svg {
	display: block;
	width: 100%;
	height: auto;
}

.map-marker {
	cursor: pointer;
}

.map-controls {
	position: absolute;
	top: 10px;
	right: 10px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.map-ctrl-btn,
.map-pan-btn {
	width: 30px;
	height: 30px;
	border-radius: 8px;
	border: 1px solid var(--mist);
	background: rgba(255, 255, 255, 0.92);
	color: var(--ocean-dark);
	font-family: 'Baloo 2', sans-serif;
	font-size: 16px;
	font-weight: 700;
	line-height: 1;
	cursor: pointer;
}

.map-pan {
	position: absolute;
	bottom: 10px;
	left: 10px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 2px;
}

.map-pan-mid {
	display: flex;
	gap: 16px;
}

.map-pan-btn {
	width: 24px;
	height: 24px;
	font-size: 10px;
}

.map-ctrl-btn:disabled,
.map-pan-btn:disabled {
	opacity: 0.45;
	cursor: default;
}

.map-legend {
	display: flex;
	flex-wrap: wrap;
	gap: 14px;
	font-size: 12px;
	color: var(--ink-soft);
}

.legend-item {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.legend-dot {
	display: inline-block;
	width: 9px;
	height: 9px;
	border-radius: 50%;
}

.player-dot {
	background: var(--coral);
}

.place-dot {
	background: #4FA871;
}

.legend-square {
	display: inline-block;
	width: 9px;
	height: 9px;
	border-radius: 2px;
	background: var(--ocean);
}

.selected-card {
	border: 1.5px solid var(--ocean-light);
}

.block-eyebrow {
	display: block;
}

.status-badge {
	display: inline-flex;
	align-items: center;
	padding: 3px 10px;
	border-radius: 999px;
	background: var(--mist);
	color: var(--ink-soft);
	font-size: 12px;
	font-weight: 600;
}

.status-badge.online {
	background: #DFF3EC;
	color: #1B6E53;
}

.nearby-row {
	display: flex;
	align-items: center;
	gap: 10px;
	width: 100%;
	padding: 10px 8px;
	border: none;
	border-top: 1px solid var(--mist);
	background: transparent;
	font-family: inherit;
	text-align: left;
	cursor: pointer;
}

.nearby-row.active {
	background: var(--mist);
	border-radius: var(--radius-sm);
}

.nearby-badge {
	flex: 0 0 12px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
}

.nearby-badge.square {
	border-radius: 3px;
}

.nearby-copy {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.nearby-copy b {
	font-size: 14px;
	color: var(--ink);
}

.nearby-distance {
	font-family: 'Baloo 2', sans-serif;
	font-size: 14px;
	font-weight: 600;
	color: var(--ocean-dark);
	white-space: nowrap;
}
</style>
