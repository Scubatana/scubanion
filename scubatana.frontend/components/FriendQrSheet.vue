<script setup lang="ts">
import QRCode from "qrcode";
import { readableMessage } from "~/utils/apiError";
import { formatFriendCode, qrRefreshDelay } from "~/utils/friends";

const emit = defineEmits<{
	close: [];
}>();

const { code, qrToken } = useFriends();
const svg = ref("");
const error = ref("");
const loading = ref(false);
const isDev = import.meta.dev;
const currentToken = ref("");
const tokenCopied = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let closed = false;

const formattedCode = computed(() => formatFriendCode(code.value));

function clearTimer(): void {
	if (timer !== null) {
		clearTimeout(timer);
		timer = null;
	}
}

async function refresh(): Promise<void> {
	clearTimer();
	loading.value = true;
	error.value = "";

	try {
		const issued = await qrToken();
		currentToken.value = issued.token;
		tokenCopied.value = false;
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
		error.value = readableMessage(caught);
	} finally {
		loading.value = false;
	}
}

async function copyToken(): Promise<void> {
	try {
		await navigator.clipboard.writeText(currentToken.value);
		tokenCopied.value = true;
	} catch {
		tokenCopied.value = false;
	}
}

function close(): void {
	emit("close");
}

onMounted(refresh);

onBeforeUnmount(() => {
	closed = true;
	clearTimer();
});
</script>

<template>
	<Teleport to="body">
		<div class="sheet-backdrop" @click.self="close">
			<div class="card stack-tight sheet" role="dialog" aria-modal="true" aria-labelledby="friend-qr-title">
				<span id="friend-qr-title" class="section-title">Mon QR ami</span>
				<p class="gallery-note">Montre ce QR à un ami : en le scannant avec Scubanion, il t'ajoute tout de suite.</p>

				<div class="qr-box" :class="{ faded: loading && svg !== '' }">
					<!-- eslint-disable-next-line vue/no-v-html -- SVG généré localement par la librairie qrcode -->
					<div v-if="svg" class="qr-image" role="img" aria-label="QR code à scanner par ton ami" v-html="svg"></div>
					<p v-else-if="loading" class="gallery-note">Chargement…</p>
				</div>

				<p class="gallery-note note-self-centered">Il change toutes les minutes : une photo partagée ne servira à rien.</p>

				<template v-if="error">
					<p class="form-error">{{ error }}</p>
					<button class="btn btn-ghost" type="button" :disabled="loading" @click="refresh">Réessayer</button>
				</template>

				<p v-if="code" class="gallery-note note-self-centered">
					Pas de caméra ? Ton code ami : <b class="friend-code-inline">{{ formattedCode }}</b>
				</p>

				<button v-if="isDev && currentToken" class="btn btn-ghost" type="button" @click="copyToken">
					{{ tokenCopied ? "Jeton copié (valable 1 min)" : "Dev : copier le jeton" }}
				</button>

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
	transition: opacity 0.2s;
}

.qr-box.faded {
	opacity: 0.5;
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

.friend-code-inline {
	font-family: 'Baloo 2', sans-serif;
	letter-spacing: 1px;
	color: var(--ocean-dark);
}
</style>
