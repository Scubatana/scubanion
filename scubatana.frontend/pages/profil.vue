<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";
import { AMBITION_LABELS, GENDER_LABELS, latestBirthDate } from "~/utils/labels";
import type { Ambition, Gender } from "~/utils/types";

const MINIMUM_AGE = 15;

const { saveProfile } = usePlayer();
const pseudo = ref("");
const birthDate = ref("");
const gender = ref<Gender | "">("");
const ambition = ref<Ambition>("fitness");
const loading = ref(false);
const fieldErrors = ref<Record<string, string>>({});
const formError = ref("");
const maxBirthDate = latestBirthDate(new Date(), MINIMUM_AGE);

async function submit(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	fieldErrors.value = {};
	formError.value = "";

	try {
		await saveProfile({
			pseudo: pseudo.value.trim(),
			birth_date: birthDate.value,
			gender: gender.value === "" ? null : gender.value,
			ambition: ambition.value
		});
		await navigateTo("/", { replace: true });
	} catch (error) {
		fieldErrors.value = error instanceof ApiError ? error.fields : {};
		formError.value = Object.keys(fieldErrors.value).length > 0 ? "" : readableMessage(error);
	} finally {
		loading.value = false;
	}
}
</script>

<template>
	<div class="page">
		<div class="topbar">
			<div class="brand">
				<img src="/img/logo.png" alt="Scubanion">
				<span>Scubanion</span>
			</div>
		</div>
		<form class="content" novalidate @submit.prevent="submit">
			<h1>Présente-toi</h1>
			<p class="gallery-note">Ces infos servent à personnaliser ton compagnon et tes quêtes.</p>
			<div class="card stack">
				<div class="field">
					<label for="pseudo">Pseudo</label>
					<input id="pseudo" v-model="pseudo" type="text" autocomplete="nickname" maxlength="20" placeholder="MarcheurDuSoir">
					<span v-if="fieldErrors.pseudo" class="field-error">{{ fieldErrors.pseudo }}</span>
				</div>
				<div class="field">
					<label for="naissance">Date de naissance</label>
					<input id="naissance" v-model="birthDate" type="date" min="1900-01-02" :max="maxBirthDate">
					<span v-if="fieldErrors.birth_date" class="field-error">{{ fieldErrors.birth_date }}</span>
				</div>
				<div class="field">
					<label for="sexe">Sexe</label>
					<select id="sexe" v-model="gender">
						<option v-for="(label, value) in GENDER_LABELS" :key="value" :value="value">{{ label }}</option>
						<option value="">Je préfère ne pas le dire</option>
					</select>
					<span v-if="fieldErrors.gender" class="field-error">{{ fieldErrors.gender }}</span>
				</div>
				<div class="field">
					<label for="ambition">Ambition</label>
					<select id="ambition" v-model="ambition">
						<option v-for="(label, value) in AMBITION_LABELS" :key="value" :value="value">{{ label }}</option>
					</select>
					<span v-if="fieldErrors.ambition" class="field-error">{{ fieldErrors.ambition }}</span>
				</div>
			</div>
			<p class="gallery-note">Ton ambition débloque des objets pour ton compagnon au fil de tes sorties.</p>
			<p v-if="formError" class="form-error">{{ formError }}</p>
			<button class="btn btn-primary" type="submit" :disabled="loading">{{ loading ? "Enregistrement…" : "Faire grandir mon compagnon" }}</button>
		</form>
	</div>
</template>
