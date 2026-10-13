<script setup lang="ts">
import { resolveLook } from "~/utils/companionLook";

const props = defineProps<{ species: string; color: string; accessories: string[]; name: string; small?: boolean }>();

const { catalog, ensureLoaded } = useCatalog();

const look = computed(() => (catalog.value ? resolveLook(catalog.value, props.species, props.color, props.accessories) : null));

onMounted(() => ensureLoaded().catch(() => undefined));
</script>

<template>
	<div class="companion-avatar" :class="{ small }" role="img" :aria-label="name">
		<div v-if="look" class="companion-avatar-base" :style="look.palette" v-html="look.species.svg"></div>
		<img v-else class="companion-avatar-base" src="/img/logo.png" alt="">
		<img v-for="accessory in look?.accessories ?? []" :key="accessory.id" class="companion-avatar-accessory" :src="accessory.image" :style="accessory.style" alt="">
	</div>
</template>
