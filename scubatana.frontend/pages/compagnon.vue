<script setup lang="ts">
import { ApiError, readableMessage } from "~/utils/apiError";
import { paletteStyle, toggleAccessory } from "~/utils/companionLook";

const { companion, load, saveCompanion } = usePlayer();
const { catalog, ensureLoaded } = useCatalog();
const name = ref("");
const species = ref("");
const color = ref("");
const accessories = ref<string[]>([]);
const loading = ref(false);
const saving = ref(false);
const loadError = ref("");
const formError = ref("");
const fieldErrors = ref<Record<string, string>>({});

const currentPalette = computed(() => paletteStyle(catalog.value?.colors.find((entry) => entry.id === color.value)));

const accessoriesByCategory = computed(() =>
	(catalog.value?.categories ?? []).map((category) => ({
		...category,
		accessories: (catalog.value?.accessories ?? []).filter((accessory) => accessory.category === category.id)
	}))
);

function fillFromCompanion(): void {
	if (companion.value === null) {
		return;
	}

	name.value = companion.value.name;
	species.value = companion.value.species;
	color.value = companion.value.color;
	accessories.value = [...companion.value.accessories];
}

async function prepare(): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	loadError.value = "";

	try {
		await ensureLoaded();

		if (companion.value === null) {
			await load();
		}

		fillFromCompanion();
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		loading.value = false;
	}
}

function toggle(accessoryId: string): void {
	accessories.value = toggleAccessory(accessories.value, accessoryId, catalog.value?.accessories ?? []);
}

async function submit(): Promise<void> {
	if (saving.value) {
		return;
	}

	saving.value = true;
	formError.value = "";
	fieldErrors.value = {};

	try {
		await saveCompanion({ name: name.value.trim(), species: species.value, color: color.value, accessories: accessories.value });
		await navigateTo("/");
	} catch (error) {
		fieldErrors.value = error instanceof ApiError ? error.fields : {};
		formError.value = fieldErrors.value.name ? "" : (Object.values(fieldErrors.value)[0] ?? readableMessage(error));
	} finally {
		saving.value = false;
	}
}

onMounted(prepare);
</script>

<template>
	<div class="page">
		<div class="topbar">
			<NuxtLink class="avatar-btn" to="/" aria-label="Retour">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg>
			</NuxtLink>
			<span class="topbar-title">Mon compagnon</span>
			<span class="topbar-spacer"></span>
		</div>
		<form v-if="catalog && species" class="form-contents" novalidate @submit.prevent="submit">
			<div class="pet-stage preview-stage">
				<CompanionAvatar :species="species" :color="color" :accessories="accessories" :name="name" />
			</div>
			<div class="content">
				<div class="card stack">
					<div class="field">
						<label for="nom">Nom</label>
						<input id="nom" v-model="name" type="text" maxlength="20" autocomplete="off">
						<span v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</span>
					</div>
					<div class="stack-tight">
						<span class="section-title">Base</span>
						<div class="option-grid">
							<button v-for="entry in catalog.species" :key="entry.id" class="option-tile" :class="{ active: species === entry.id }" type="button" :aria-pressed="species === entry.id" @click="species = entry.id">
								<span class="option-svg" :style="currentPalette" v-html="entry.svg"></span>
								<span>{{ entry.label }}</span>
							</button>
						</div>
					</div>
					<div class="stack-tight">
						<span class="section-title">Couleur</span>
						<div class="swatch-row">
							<button v-for="entry in catalog.colors" :key="entry.id" class="swatch" :class="{ active: color === entry.id }" type="button" :style="{ background: entry.main }" :aria-label="entry.label" :aria-pressed="color === entry.id" @click="color = entry.id"></button>
						</div>
					</div>
				</div>
				<div v-for="category in accessoriesByCategory" :key="category.id" class="card stack-tight">
					<span class="section-title">{{ category.label }}</span>
					<div class="option-grid">
						<button v-for="accessory in category.accessories" :key="accessory.id" class="option-tile" :class="{ active: accessories.includes(accessory.id) }" type="button" :aria-pressed="accessories.includes(accessory.id)" @click="toggle(accessory.id)">
							<img :src="accessory.image" alt="">
							<span>{{ accessory.label }}</span>
						</button>
					</div>
				</div>
				<p v-if="formError" class="form-error">{{ formError }}</p>
				<button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? "Enregistrement…" : "Enregistrer" }}</button>
			</div>
		</form>
		<div v-else class="content centered">
			<p v-if="loading" class="gallery-note note-centered note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="prepare">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
