<script setup lang="ts">
import { FLAME_INNER_PATH, FLAME_OUTER_PATH } from "~/utils/icons";
import type { Streak } from "~/utils/types";

const props = defineProps<{ streak: Streak }>();

const label = computed(() => {
	if (props.streak.days === 0) {
		return "Aucune série en cours";
	}

	const days = props.streak.days === 1 ? "1 jour" : `${props.streak.days} jours`;

	return props.streak.active ? `Série de ${days}, objectif du jour atteint` : `Série de ${days}, à prolonger aujourd'hui`;
});
</script>

<template>
	<span class="streak" :class="{ active: streak.active, alive: streak.days > 0 }" role="img" :aria-label="label">
		<svg viewBox="0 0 24 24" aria-hidden="true">
			<path class="streak-outer" :d="FLAME_OUTER_PATH"/>
			<path class="streak-inner" :d="FLAME_INNER_PATH"/>
		</svg>
		<b>{{ streak.days }}</b>
	</span>
</template>
