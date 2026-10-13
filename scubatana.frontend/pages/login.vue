<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";

const auth = useAuth();
const email = ref(auth.pendingEmail.value ?? "");
const loading = ref(false);
const fieldError = ref("");
const formError = ref("");

async function submit(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	fieldError.value = "";
	formError.value = "";

	try {
		await auth.requestCode(email.value);
		await navigateTo("/verification");
	} catch (error) {
		fieldError.value = error instanceof ApiError && error.fields.email ? "Cette adresse email est invalide." : "";
		formError.value = fieldError.value ? "" : readableMessage(error);
	} finally {
		loading.value = false;
	}
}
</script>

<template>
	<div class="page">
		<div class="content centered">
			<div class="pet-stage">
				<img class="pet-img" src="/img/logo.png" alt="Scubanion">
				<h1>Scubanion</h1>
				<p class="gallery-note note-centered">Sors marcher, croise du monde, fais grandir ton compagnon.</p>
			</div>
			<form class="card stack" novalidate @submit.prevent="submit">
				<div class="field">
					<label for="email">Email</label>
					<input id="email" v-model="email" type="email" inputmode="email" autocomplete="email" autocapitalize="none" placeholder="toi@exemple.fr">
					<span v-if="fieldError" class="field-error">{{ fieldError }}</span>
				</div>
				<p v-if="formError" class="form-error">{{ formError }}</p>
				<button class="btn btn-primary" type="submit" :disabled="loading">{{ loading ? "Envoi…" : "Continuer" }}</button>
			</form>
			<p class="link-under">Pas de compte ? La suite se fait automatiquement.</p>
		</div>
	</div>
</template>
