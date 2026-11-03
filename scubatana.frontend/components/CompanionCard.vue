<script setup lang="ts">
import { companionBubble, companionPosture, companionStateLabel } from "~/utils/companionMood";
import { formatCount } from "~/utils/labels";
import type { Companion } from "~/utils/types";

const props = defineProps<{ companion: Companion }>();

const xpFill = computed(() => `${Math.min(100, Math.round((props.companion.xp / props.companion.xp_for_next_level) * 100))}%`);
const moodFill = computed(() => `${Math.max(0, Math.min(100, Math.round(props.companion.mood)))}%`);
const healthFill = computed(() => `${Math.max(0, Math.min(100, Math.round(props.companion.health)))}%`);
const posture = computed(() => companionPosture(props.companion));
const stateLabel = computed(() => companionStateLabel(props.companion));
const bubble = computed(() => companionBubble(props.companion));
</script>

<template>
	<div class="pet-stage">
		<div class="bubble">{{ bubble }}</div>
		<NuxtLink to="/compagnon" aria-label="Personnaliser ton compagnon">
			<CompanionAvatar :species="companion.species" :color="companion.color" :accessories="companion.accessories" :name="companion.name" :posture="posture" />
		</NuxtLink>
		<h1 class="companion-name">{{ companion.name }}</h1>
		<div class="companion-badges">
			<span class="pill">Niveau {{ companion.level }}</span>
			<span class="pill pill-soft">{{ stateLabel }}</span>
		</div>
		<div class="xp-row">
			<div class="wave-bar thin" :style="{ '--fill': xpFill }"><span></span></div>
			<span class="eyebrow">{{ formatCount(companion.xp) }} / {{ formatCount(companion.xp_for_next_level) }} XP</span>
		</div>
		<div class="vital-grid">
			<div class="vital-meter">
				<div class="row-between">
					<span class="eyebrow">Humeur</span>
					<span class="eyebrow">{{ Math.round(companion.mood) }} %</span>
				</div>
				<div class="wave-bar thin" :style="{ '--fill': moodFill }"><span></span></div>
			</div>
			<div class="vital-meter">
				<div class="row-between">
					<span class="eyebrow">Santé</span>
					<span class="eyebrow">{{ Math.round(companion.health) }} %</span>
				</div>
				<div class="wave-bar thin vital-health" :style="{ '--fill': healthFill }"><span></span></div>
			</div>
		</div>
	</div>
</template>
