<script setup lang="ts">
defineProps<{ title: string }>();
</script>

<template>
	<div class="page">
		<div class="topbar">
			<span class="topbar-spacer"></span>
			<span class="topbar-title">{{ title }}</span>
			<span class="topbar-spacer"></span>
		</div>
		<div class="content centered">
			<div class="pet-stage">
				<img class="pet-img small" src="/img/logo.png" alt="Scubanion">
				<h1 class="companion-name">Bientôt disponible</h1>
				<p class="gallery-note note-centered">Cette partie de Scubanion arrive dans une prochaine version.</p>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
