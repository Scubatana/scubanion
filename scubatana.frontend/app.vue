<script setup lang="ts">
const { lastNotice, lastError, dismissFeedback, startListening } = usePassages();
const session = useSession();

onMounted(() => {
	void startListening();
});

watch(session.token, (token) => {
	if (token !== null) {
		void usePassages().flush().catch(() => undefined);
	}
});

useHead({
	htmlAttrs: { lang: "fr" }
});
</script>

<template>
	<div class="phone embedded">
		<div class="screen">
			<div class="tide"></div>
			<div v-if="lastNotice || lastError" class="passage-banner" :class="{ error: !!lastError }" :role="lastError ? 'alert' : 'status'">
				<span>{{ lastError || lastNotice }}</span>
				<button class="passage-close" type="button" aria-label="Fermer la notification" @click="dismissFeedback">×</button>
			</div>
			<NuxtPage />
		</div>
	</div>
</template>

<style scoped>
.passage-banner {
	position: relative;
	z-index: 20;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	margin: 10px 14px 0;
	padding: 10px 12px;
	border-radius: 12px;
	background: #DFF3EC;
	color: #1B6E53;
	font-size: 13px;
	font-weight: 600;
}

.passage-banner.error {
	background: #FDEAE6;
	color: #A83823;
}

.passage-close {
	border: none;
	background: transparent;
	color: inherit;
	font-size: 18px;
	line-height: 1;
	cursor: pointer;
}
</style>
