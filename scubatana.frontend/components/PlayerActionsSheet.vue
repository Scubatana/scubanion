<script setup lang="ts">
const props = defineProps<{
	pseudo: string;
	canRemove: boolean;
	busy: boolean;
	error: string;
}>();

const emit = defineEmits<{
	close: [];
	remove: [];
	block: [];
	report: [];
}>();

const step = ref<"menu" | "remove" | "block">("menu");

function close(): void {
	if (!props.busy) {
		emit("close");
	}
}
</script>

<template>
	<Teleport to="body">
		<div class="sheet-backdrop" @click.self="close">
			<div class="card stack-tight sheet" role="dialog" aria-modal="true" aria-labelledby="player-actions-title">
				<template v-if="step === 'menu'">
					<span id="player-actions-title" class="section-title">{{ pseudo }}</span>
					<button v-if="canRemove" class="sheet-action" type="button" @click="step = 'remove'">Retirer de mes amis</button>
					<button class="sheet-action" type="button" @click="step = 'block'">Bloquer</button>
					<button class="sheet-action danger" type="button" @click="emit('report')">Signaler</button>
					<button class="btn btn-ghost" type="button" @click="close">Annuler</button>
				</template>

				<template v-else-if="step === 'remove'">
					<span id="player-actions-title" class="section-title">Retirer {{ pseudo }} ?</span>
					<p class="gallery-note">Vous ne verrez plus vos pas l'un de l'autre. Vous pourrez vous ajouter de nouveau plus tard.</p>
					<p v-if="error" class="form-error">{{ error }}</p>
					<button class="btn btn-danger" type="button" :disabled="busy" @click="emit('remove')">
						{{ busy ? "Retrait…" : "Retirer" }}
					</button>
					<button class="btn btn-ghost" type="button" :disabled="busy" @click="step = 'menu'">Retour</button>
				</template>

				<template v-else>
					<span id="player-actions-title" class="section-title">Bloquer {{ pseudo }} ?</span>
					<p class="gallery-note">
						{{ canRemove ? `${pseudo} sera retiré de tes amis. ` : "" }}Vous ne pourrez plus vous ajouter, ni par code ni par QR. Tu pourras le débloquer depuis « Joueurs bloqués ».
					</p>
					<p v-if="error" class="form-error">{{ error }}</p>
					<button class="btn btn-danger" type="button" :disabled="busy" @click="emit('block')">
						{{ busy ? "Blocage…" : "Bloquer" }}
					</button>
					<button class="btn btn-ghost" type="button" :disabled="busy" @click="step = 'menu'">Retour</button>
				</template>
			</div>
		</div>
	</Teleport>
</template>

<style scoped>
.sheet-backdrop {
	position: fixed;
	inset: 0;
	z-index: 50;
	display: flex;
	align-items: flex-end;
	justify-content: center;
	padding: 16px;
	background: rgba(30, 58, 66, 0.45);
}

.sheet {
	width: 100%;
	max-width: 480px;
	max-height: calc(100vh - 32px);
	overflow-y: auto;
}

.sheet-action {
	width: 100%;
	min-height: 48px;
	padding: 0 4px;
	border: none;
	border-bottom: 1px solid var(--mist);
	background: none;
	text-align: left;
	font-family: inherit;
	font-size: 15px;
	font-weight: 600;
	color: var(--ink);
	cursor: pointer;
}

.sheet-action.danger {
	color: var(--coral-dark);
	border-bottom: none;
}
</style>
