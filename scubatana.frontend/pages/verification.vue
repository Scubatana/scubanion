<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";

const auth = useAuth();
const code = ref("");
const termsAccepted = ref(false);
const loading = ref(false);
const resending = ref(false);
const codeError = ref("");
const termsError = ref("");
const formError = ref("");
const notice = ref("");

if (auth.pendingEmail.value === null) {
	await navigateTo("/login", { replace: true });
}

function resetMessages(): void {
	codeError.value = "";
	termsError.value = "";
	formError.value = "";
	notice.value = "";
}

async function submit(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	resetMessages();

	try {
		await auth.verifyCode(code.value, termsAccepted.value);
		await navigateTo("/", { replace: true });
	} catch (error) {
		const fields = error instanceof ApiError ? error.fields : {};

		codeError.value = fields.code ? "Ce code est incorrect ou expiré." : "";
		termsError.value = fields.terms_accepted ? "Accepte les conditions d'utilisation pour créer ton compte." : "";
		formError.value = codeError.value || termsError.value ? "" : readableMessage(error);
	} finally {
		loading.value = false;
	}
}

async function resend(): Promise<void> {
	if (resending.value || auth.pendingEmail.value === null) {
		return;
	}

	resending.value = true;
	resetMessages();

	try {
		await auth.requestCode(auth.pendingEmail.value);
		code.value = "";
		notice.value = "Un nouveau code vient d'être envoyé.";
	} catch (error) {
		formError.value = readableMessage(error);
	} finally {
		resending.value = false;
	}
}
</script>

<template>
	<div class="page">
		<div class="content centered">
			<div class="pet-stage">
				<img class="pet-img small" src="/img/logo.png" alt="Scubanion">
				<h1 class="companion-name">Vérifie ton code</h1>
				<p class="gallery-note note-centered">Un code à 6 chiffres vient d'être envoyé à {{ auth.pendingEmail.value }}.</p>
			</div>
			<form class="card stack" novalidate @submit.prevent="submit">
				<div class="field">
					<label for="code">Code</label>
					<input id="code" v-model="code" class="code-input" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="12" placeholder="123456">
					<span v-if="codeError" class="field-error">{{ codeError }}</span>
				</div>
				<label class="checkbox-row">
					<input v-model="termsAccepted" type="checkbox">
					Accepter les conditions d'utilisation
				</label>
				<span v-if="termsError" class="field-error">{{ termsError }}</span>
				<p v-if="formError" class="form-error">{{ formError }}</p>
				<p v-if="notice" class="gallery-note note-centered">{{ notice }}</p>
				<button class="btn btn-primary" type="submit" :disabled="loading">{{ loading ? "Vérification…" : "Confirmer" }}</button>
			</form>
			<button class="link-under" type="button" :disabled="resending" @click="resend">Renvoyer le code</button>
			<NuxtLink class="link-under" to="/login">Changer d'adresse email</NuxtLink>
		</div>
	</div>
</template>
