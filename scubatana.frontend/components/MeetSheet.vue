<script setup lang="ts">
import QRCode from "qrcode";
import { readableMessage } from "~/utils/apiError";
import { qrRefreshDelay } from "~/utils/friends";
import { actionMessage } from "~/utils/quests";

const emit = defineEmits<{
	close: [];
}>();

const { qrToken } = useFriends();
const { scanAndMeet } = useEncounters();
const svg = ref("");
const qrError = ref("");
const scanning = ref(false);
const success = ref("");
const scanError = ref("");
let timer: ReturnType<typeof setTimeout> | null = null;
let closed = false;

function clearTimer(): void {
	if (timer !== null) {
		clearTimeout(timer);
		timer = null;
	}
}

async function refresh(): Promise<void> {
	clearTimer();
	qrError.value = "";

	try {
		const issued = await qrToken();
		svg.value = await QRCode.toString(issued.token, {
			type: "svg",
			margin: 1,
			errorCorrectionLevel: "M",
			color: { dark: "#1E3A42", light: "#FFFFFF" }
		});

		if (!closed) {
			timer = setTimeout(refresh, qrRefreshDelay(issued.expires_at));
		}
	} catch (caught) {
		svg.value = "";
		qrError.value = readableMessage(caught);
	}
}

async function scan(): Promise<void> {
	if (scanning.value) {
		return;
	}

	scanning.value = true;
	success.value = "";
	scanError.value = "";

	try {
		success.value = (await scanAndMeet()) ?? "";
	} catch (caught) {
		scanError.value = actionMessage(caught);
	} finally {
		scanning.value = false;
	}
}

function close(): void {
	emit("close");
}

onMounted(() => {
	// L'autre joueur ne peut valider la rencontre que si le serveur connaît notre position récente.
	void usePosition().send();
	void refresh();
});

onBeforeUnmount(() => {
	closed = true;
	clearTimer();
});
</script>

<template>
	<Teleport to="body">
		<div class="sheet-backdrop" @click.self="close">
			<div class="card stack-tight sheet" role="dialog" aria-modal="true" aria-labelledby="meet-title">
				<span id="meet-title" class="section-title">Valider une rencontre</span>
				<p class="gallery-note">Vous êtes ensemble ? L'un montre son QR, l'autre le scanne. Gardez tous les deux la localisation activée : vos positions doivent se recouper.</p>
				<div class="qr-box">
					<div v-if="svg" class="qr-image" role="img" aria-label="Mon QR, à scanner par l'autre joueur" v-html="svg"></div>
					<p v-else-if="!qrError" class="gallery-note">Chargement…</p>
				</div>
				<p class="gallery-note note-self-centered">Il change toutes les minutes : une photo partagée ne servira à rien.</p>
				<template v-if="qrError">
					<p class="form-error">{{ qrError }}</p>
					<button class="btn btn-ghost" type="button" @click="refresh">Réessayer</button>
				</template>
				<button class="btn btn-ghost" type="button" :disabled="scanning" @click="scan">{{ scanning ? "Vérification…" : "Scanner le QR de l'autre joueur" }}</button>
				<p v-if="success" class="quest-notice" role="status">{{ success }}</p>
				<p v-if="scanError" class="form-error">{{ scanError }}</p>
				<button class="btn btn-primary" type="button" @click="close">Fermer</button>
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

.qr-box {
	align-self: center;
	width: min(72vw, 260px);
	aspect-ratio: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 12px;
	border-radius: var(--radius-md);
	background: var(--white);
	box-shadow: 0 0 0 1px var(--mist);
}

.qr-image {
	width: 100%;
	height: 100%;
}

.qr-image :deep(svg) {
	display: block;
	width: 100%;
	height: 100%;
}
</style>
