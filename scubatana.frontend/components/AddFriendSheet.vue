<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";
import { formatFriendCode } from "~/utils/friends";
import { QrScanError, scanQrCode } from "~/utils/qrScanner";

const emit = defineEmits<{
	close: [];
	added: [message: string];
	showQr: [];
}>();

const { code, invite, scanQr } = useFriends();
const inviteCode = ref("");
const error = ref("");
const copied = ref(false);
const busy = ref<"invite" | "scan" | null>(null);
const isDev = import.meta.dev;
const devToken = ref("");

const formattedCode = computed(() => formatFriendCode(code.value));

function close(): void {
	if (busy.value === null) {
		emit("close");
	}
}

useSheet(close);

async function submitInvite(): Promise<void> {
	if (busy.value !== null || inviteCode.value.trim() === "") {
		return;
	}

	busy.value = "invite";
	error.value = "";

	try {
		const outcome = await invite(inviteCode.value);
		emit("added", outcome === "mutual" ? "Invitation réciproque : vous êtes maintenant amis !" : "Invitation envoyée.");
	} catch (caught) {
		error.value = caught instanceof ApiError && caught.fields.code ? caught.fields.code : readableMessage(caught);
	} finally {
		busy.value = null;
	}
}

async function scanFriendQr(): Promise<void> {
	if (busy.value !== null) {
		return;
	}

	error.value = "";

	let token: string | null;

	try {
		token = await scanQrCode();
	} catch (caught) {
		error.value = caught instanceof QrScanError ? caught.message : readableMessage(caught);
		return;
	}

	if (token !== null) {
		await addFriendByQr(token);
	}
}

async function addFriendByQr(token: string): Promise<void> {
	if (busy.value !== null || token.trim() === "") {
		return;
	}

	busy.value = "scan";
	error.value = "";

	try {
		const pseudo = await scanQr(token);
		emit("added", pseudo ? `${pseudo} fait maintenant partie de tes amis !` : "Vous êtes maintenant amis !");
	} catch (caught) {
		error.value = caught instanceof ApiError && caught.fields.token ? caught.fields.token : readableMessage(caught);
	} finally {
		busy.value = null;
	}
}

async function copyCode(): Promise<void> {
	if (!code.value || typeof navigator === "undefined" || !navigator.clipboard) {
		return;
	}

	try {
		await navigator.clipboard.writeText(code.value);
		copied.value = true;
		setTimeout(() => {
			copied.value = false;
		}, 2000);
	} catch {
		copied.value = false;
	}
}
</script>

<template>
	<Teleport to="body">
		<div class="sheet-backdrop" @click.self="close">
			<form class="card stack-tight sheet" role="dialog" aria-modal="true" aria-labelledby="add-friend-title" novalidate @submit.prevent="submitInvite">
				<span id="add-friend-title" class="section-title">Ajouter un ami</span>

				<button class="btn btn-primary scan-btn" type="button" :disabled="busy !== null" @click="scanFriendQr">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
						<path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16M4 12h16"/>
					</svg>
					{{ busy === "scan" ? "Ajout…" : "Scanner le QR d'un ami" }}
				</button>
				<button class="btn btn-ghost" type="button" :disabled="busy !== null" @click="emit('showQr')">Afficher mon QR</button>

				<div class="field">
					<label for="friend-code-input">Ou avec son code ami</label>
					<div class="invite-box">
						<input
							id="friend-code-input"
							v-model="inviteCode"
							type="text"
							autocomplete="off"
							autocapitalize="characters"
							maxlength="16"
							placeholder="Ex : K7Q2-MZ4P"
						>
						<button class="btn btn-primary" type="submit" :disabled="busy !== null || inviteCode.trim().length === 0">
							{{ busy === "invite" ? "…" : "Inviter" }}
						</button>
					</div>
					<span v-if="error" class="field-error" role="alert">{{ error }}</span>
				</div>

				<p v-if="code" class="own-code">
					Ton code : <b>{{ formattedCode }}</b>
					<button class="inline-action" type="button" @click="copyCode">{{ copied ? "Copié" : "Copier" }}</button>
				</p>

				<div v-if="isDev" class="field dev-field">
					<label for="dev-qr-token">Dev : coller un jeton QR (sans caméra)</label>
					<div class="invite-box">
						<input id="dev-qr-token" v-model="devToken" type="text" autocomplete="off" placeholder="eyJpdiI6...">
						<button class="btn btn-ghost" type="button" :disabled="busy !== null || devToken.trim() === ''" @click="addFriendByQr(devToken)">
							Ajouter
						</button>
					</div>
				</div>

				<button class="btn btn-ghost" type="button" :disabled="busy !== null" @click="close">Fermer</button>
			</form>
		</div>
	</Teleport>
</template>

<style scoped>
.invite-box input {
	min-width: 0;
	width: 100%;
}

.invite-box .btn {
	flex: 0 0 auto;
	box-shadow: none;
}

.scan-btn {
	gap: 8px;
}

.scan-btn svg {
	width: 18px;
	height: 18px;
	stroke: currentColor;
}

.own-code {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	font-size: 13px;
	color: var(--ink-soft);
}

.own-code b {
	font-family: 'Baloo 2', sans-serif;
	letter-spacing: 1px;
	color: var(--ocean-dark);
}

.inline-action {
	padding: 8px 6px;
	border: none;
	background: none;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: var(--ocean);
	cursor: pointer;
}

.dev-field {
	padding: 10px;
	border: 1px dashed var(--ocean-light);
	border-radius: var(--radius-sm);
}
</style>
