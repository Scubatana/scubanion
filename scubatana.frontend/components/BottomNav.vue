<script setup lang="ts">
const route = useRoute();

const items = [
	{ to: "/", label: "Accueil", icon: "home" },
	{ to: "/amis", label: "Amis", icon: "friends" },
	{ to: "/chat", label: "Chat", icon: "chat" },
	{ to: "/carte", label: "Carte", icon: "map" },
	{ to: "/quetes", label: "Quêtes", icon: "quests" }
];
</script>

<template>
	<nav class="bottomnav">
		<NuxtLink v-for="item in items" :key="item.to" class="navitem" :class="{ active: route.path === item.to }" :to="item.to">
			<svg v-if="item.icon === 'home'" viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
			<svg v-else-if="item.icon === 'friends'" viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.6"/><path d="M2 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M14.5 20c0-2.6 1.9-4.6 4.3-5"/></svg>
			<svg v-else-if="item.icon === 'chat'" viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 5h16v11H9l-5 4V5z"/></svg>
			<svg v-else-if="item.icon === 'map'" viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M9 4l-6 2v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
			<svg v-else viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4"/><circle cx="12" cy="16.8" r="0.4" fill="currentColor"/></svg>
			<span>{{ item.label }}</span>
		</NuxtLink>
	</nav>
</template>
