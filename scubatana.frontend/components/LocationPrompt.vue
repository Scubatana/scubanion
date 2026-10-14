<script setup lang="ts">
const emit = defineEmits<{ settings: []; later: [] }>();
</script>

<template>
	<div class="prompt-backdrop" role="dialog" aria-modal="true" aria-labelledby="location-prompt-title">
		<div class="card stack-tight prompt-card">
			<span id="location-prompt-title" class="section-title">Localisation désactivée</span>
			<p class="gallery-note">Ta position sert à valider les lieux de tes quêtes quand tu y passes. Autorise la localisation exacte dans les réglages de l'app.</p>
			<button class="btn btn-primary" type="button" @click="emit('settings')">Ouvrir les réglages</button>
			<button class="btn btn-ghost" type="button" @click="emit('later')">Plus tard</button>
		</div>
	</div>
</template>
