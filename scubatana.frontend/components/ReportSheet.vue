<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";
import { REPORT_COMMENT_MAX, REPORT_REASONS, type ReportReason } from "~/utils/safety";

const props = defineProps<{
	playerId: number;
	pseudo: string;
}>();

const emit = defineEmits<{
	close: [];
	done: [result: { message: string; blocked: boolean }];
}>();

const { report, block } = useSafety();
const reason = ref<ReportReason | null>(null);
const comment = ref("");
const alsoBlock = ref(true);
const sending = ref(false);
const error = ref("");

async function submit(): Promise<void> {
	if (sending.value || reason.value === null) {
		return;
	}

	sending.value = true;
	error.value = "";

	try {
		const message = await report(props.playerId, reason.value, comment.value);

		if (alsoBlock.value) {
			await block(props.playerId);
		}

		emit("done", { message, blocked: alsoBlock.value });
	} catch (caught) {
		error.value = caught instanceof ApiError ? (Object.values(caught.fields)[0] ?? readableMessage(caught)) : readableMessage(caught);
	} finally {
		sending.value = false;
	}
}

function close(): void {
	if (!sending.value) {
		emit("close");
	}
}

useSheet(close);
</script>

<template>
	<Teleport to="body">
		<div class="sheet-backdrop" @click.self="close">
			<form class="card stack-tight sheet" role="dialog" aria-modal="true" aria-labelledby="report-title" novalidate @submit.prevent="submit">
				<span id="report-title" class="section-title">Signaler {{ pseudo }}</span>
				<p class="gallery-note">L'équipe examinera ton signalement. {{ pseudo }} ne saura pas que tu l'as signalé.</p>

				<div class="field">
					<label>Motif</label>
					<div class="chip-tabs reason-list" role="radiogroup" aria-label="Motif du signalement">
						<button
							v-for="option in REPORT_REASONS"
							:key="option.value"
							class="chip"
							:class="{ active: reason === option.value }"
							type="button"
							role="radio"
							:aria-checked="reason === option.value"
							@click="reason = option.value"
						>
							{{ option.label }}
						</button>
					</div>
				</div>

				<div class="field">
					<label for="report-comment">Commentaire (facultatif)</label>
					<textarea
						id="report-comment"
						v-model="comment"
						rows="3"
						:maxlength="REPORT_COMMENT_MAX"
						placeholder="Ce qui s'est passé, en quelques mots"
					></textarea>
				</div>

				<label class="block-option">
					<input v-model="alsoBlock" type="checkbox">
					<span>Bloquer aussi {{ pseudo }}</span>
				</label>

				<p v-if="error" class="form-error">{{ error }}</p>

				<button class="btn btn-danger" type="submit" :disabled="sending || reason === null">
					{{ sending ? "Envoi…" : "Envoyer le signalement" }}
				</button>
				<button class="btn btn-ghost" type="button" :disabled="sending" @click="close">Annuler</button>
			</form>
		</div>
	</Teleport>
</template>

<style scoped>
.reason-list {
	flex-wrap: wrap;
}

.reason-list .chip {
	border: none;
	font-family: inherit;
	cursor: pointer;
}

textarea {
	width: 100%;
	box-sizing: border-box;
	resize: vertical;
	padding: 10px 12px;
	border: 1px solid var(--mist);
	border-radius: var(--radius-sm);
	font-family: 'Inter', sans-serif;
	font-size: 14px;
	color: var(--ink);
}

.block-option {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 14px;
	color: var(--ink);
}

.block-option input {
	width: 18px;
	height: 18px;
	accent-color: var(--ocean);
}
</style>
