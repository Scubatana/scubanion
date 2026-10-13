<script setup lang="ts">
import { formatCount, formatDay } from "~/utils/labels";
import type { Today } from "~/utils/types";

const props = defineProps<{ today: Today }>();

const fill = computed(() => (props.today.goal > 0 ? `${Math.min(100, Math.round((props.today.steps / props.today.goal) * 100))}%` : "0%"));
</script>

<template>
	<div class="card stack-tight">
		<div class="row-between">
			<span class="section-title">{{ formatDay(today.date) }}</span>
			<span class="eyebrow">Objectif {{ formatCount(today.goal) }} pas</span>
		</div>
		<div class="wave-bar" :style="{ '--fill': fill }"><span></span></div>
		<span class="steps">{{ formatCount(today.steps) }} pas</span>
	</div>
</template>
