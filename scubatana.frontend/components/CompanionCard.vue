<script setup lang="ts">
import { companionBubble } from "~/utils/companionMood";
import { formatCount } from "~/utils/labels";
import type { Companion } from "~/utils/types";

const props = defineProps<{ companion: Companion }>();

const xpFill = computed(() => `${Math.min(100, Math.round((props.companion.xp / props.companion.xp_for_next_level) * 100))}%`);

const bubble = computed(() => companionBubble(props.companion));
</script>

<template>
	<div class="pet-stage">
		<div class="bubble">{{ bubble }}</div>
		<NuxtLink to="/compagnon" aria-label="Personnaliser ton compagnon">
			<CompanionAvatar :species="companion.species" :color="companion.color" :accessories="companion.accessories" :name="companion.name" />
		</NuxtLink>
		<h1 class="companion-name">{{ companion.name }}</h1>
		<span class="pill">Niveau {{ companion.level }}</span>
		<div class="xp-row">
			<div class="wave-bar thin" :style="{ '--fill': xpFill }"><span></span></div>
			<span class="eyebrow">{{ formatCount(companion.xp) }} / {{ formatCount(companion.xp_for_next_level) }} XP</span>
		</div>
	</div>
</template>
