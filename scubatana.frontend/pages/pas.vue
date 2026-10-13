<script setup lang="ts">
import { readableMessage } from "~/utils/apiError";
import { FLAME_OUTER_PATH } from "~/utils/icons";
import { formatCount } from "~/utils/labels";
import { calendarCells, longDayLabel, monthLabel, shiftMonth, shortDayLabel, withLiveToday, type CalendarCell, type StepHistory } from "~/utils/stepHistory";
import { localDay } from "~/utils/stepLedger";

const WEEKDAYS = ["L", "M", "M", "J", "V", "S", "D"];

const api = useApi();
const steps = useSteps();
const { saveGoal } = usePlayer();

const GOAL_STEP = 500;
const GOAL_MIN = 1000;
const GOAL_MAX = 50000;

const savingGoal = ref(false);
const loaded = ref<StepHistory | null>(null);
const selectedDay = ref<string | null>(null);
const loading = ref(false);
const loadError = ref("");

const history = computed(() => (loaded.value ? withLiveToday(loaded.value, localDay(new Date()), steps.displayedSteps.value) : null));

const cells = computed(() => (history.value ? calendarCells(history.value.month, history.value.days, history.value.goal) : []));

const selected = computed(() => cells.value.find((cell) => cell.day !== null && cell.day === selectedDay.value) ?? null);

async function open(month: string | null): Promise<void> {
	if (loading.value) {
		return;
	}

	loading.value = true;
	loadError.value = "";

	try {
		loaded.value = await api.get<StepHistory>(month ? `/me/steps?month=${month}` : "/me/steps");
		selectedDay.value = cells.value.find((cell) => cell.day === localDay(new Date()) && cell.steps !== null)?.day ?? null;
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		loading.value = false;
	}
}

function move(offset: number): void {
	if (history.value) {
		void open(shiftMonth(history.value.month, offset));
	}
}

async function changeGoal(offset: number): Promise<void> {
	const goal = (history.value?.goal ?? 0) + offset;

	if (savingGoal.value || loaded.value === null || goal < GOAL_MIN || goal > GOAL_MAX) {
		return;
	}

	savingGoal.value = true;
	loadError.value = "";

	try {
		await saveGoal(goal);
		loaded.value = await api.get<StepHistory>(`/me/steps?month=${loaded.value.month}`);
	} catch (error) {
		loadError.value = readableMessage(error);
	} finally {
		savingGoal.value = false;
	}
}

function select(cell: CalendarCell): void {
	if (cell.day !== null && cell.steps !== null) {
		selectedDay.value = selectedDay.value === cell.day ? null : cell.day;
	}
}

onMounted(async () => {
	await steps.sync();
	await open(null);
});
</script>

<template>
	<div class="page">
		<div class="topbar">
			<NuxtLink class="avatar-btn" to="/" aria-label="Retour">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg>
			</NuxtLink>
			<span class="topbar-title">Mes pas</span>
			<span class="topbar-spacer"></span>
		</div>
		<div v-if="history" class="month-nav">
			<button class="avatar-btn" type="button" aria-label="Mois précédent" :disabled="loading || !history.has_previous" @click="move(-1)">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg>
			</button>
			<h1 class="companion-name">{{ monthLabel(history.month) }}</h1>
			<button class="avatar-btn" type="button" aria-label="Mois suivant" :disabled="loading || !history.has_next" @click="move(1)">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M9 5l7 7-7 7"/></svg>
			</button>
		</div>
		<div class="content">
			<div v-if="history" class="card row-center">
				<div class="hero-steps">
					<svg class="hero-icon" viewBox="0 0 24 24" aria-hidden="true"><g transform="rotate(-10 7.5 10)"><path d="M7.5 2.5c2.2 0 3.6 2 3.6 4.6 0 2.2-0.9 3.2-0.9 4.800H4.800c0-1.6-0.9-2.6-0.9-4.8 0-2.6 1.4-4.6 3.6-4.600z"/><rect x="4.9" y="13.2" width="5.2" height="3.6" rx="1.8"/></g><g transform="rotate(10 16.5 14)"><path d="M16.5 7c2.2 0 3.6 2 3.6 4.6 0 2.2-0.9 3.2-0.9 4.800h-5.400c0-1.6-0.9-2.6-0.9-4.8 0-2.6 1.4-4.6 3.6-4.600z"/><rect x="13.9" y="17.7" width="5.2" height="3.6" rx="1.8"/></g></svg>
					<span class="steps">{{ formatCount(history.stats.total) }}</span>
				</div>
				<StreakFlame :streak="history.streak" />
			</div>
			<div v-if="history" class="card stack-tight">
				<div class="calendar">
					<span v-for="(weekday, index) in WEEKDAYS" :key="index" class="calendar-weekday">{{ weekday }}</span>
					<button v-for="cell in cells" :key="cell.key" class="calendar-day" :class="{ blank: cell.day === null, reached: cell.reached, future: cell.day !== null && cell.steps === null, selected: selected?.day === cell.day && cell.day !== null }" type="button" :disabled="cell.steps === null" :style="{ '--fill': `${cell.fill}%` }" :aria-label="cell.day ? longDayLabel(cell.day) : undefined" @click="select(cell)">
						<span>{{ cell.number }}</span>
					</button>
				</div>
				<div v-if="selected && selected.day" class="day-detail">
					<span>{{ shortDayLabel(selected.day) }}</span>
					<b>{{ formatCount(selected.steps ?? 0) }}</b>
					<svg v-if="selected.reached" viewBox="0 0 24 24" aria-label="Objectif atteint"><circle cx="12" cy="12" r="10"/><path d="M7.5 12.5l3 3 6-6.5"/></svg>
				</div>
			</div>
			<div v-if="history" class="card row-center" aria-label="Objectif de pas par jour">
				<button class="avatar-btn goal-btn" type="button" aria-label="Baisser l'objectif" :disabled="savingGoal || history.goal <= GOAL_MIN" @click="changeGoal(-GOAL_STEP)">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 12h12"/></svg>
				</button>
				<div class="hero-steps">
					<svg class="goal-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle class="solid" cx="12" cy="12" r="1.6"/></svg>
					<span class="goal-value">{{ formatCount(history.goal) }}</span>
				</div>
				<button class="avatar-btn goal-btn" type="button" aria-label="Augmenter l'objectif" :disabled="savingGoal || history.goal >= GOAL_MAX" @click="changeGoal(GOAL_STEP)">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 12h12M12 6v12"/></svg>
				</button>
			</div>
			<div v-if="history" class="stat-grid">
				<div class="card stat-tile" aria-label="Jours où l'objectif est atteint">
					<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle class="solid" cx="12" cy="12" r="1.6"/></svg>
					<b>{{ history.stats.goal_days }}</b>
					<span>objectifs</span>
				</div>
				<div class="card stat-tile" aria-label="Plus longue série du mois">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path :d="FLAME_OUTER_PATH"/></svg>
					<b>{{ history.stats.longest_streak }}</b>
					<span>série max</span>
				</div>
				<div class="card stat-tile" aria-label="Meilleur jour du mois">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 5.5H4c0 3 1.5 4.5 3.5 4.800M17 5.5h3c0 3-1.5 4.5-3.5 4.800M12 14v4M8.5 20.5h7"/></svg>
					<b>{{ history.stats.best ? formatCount(history.stats.best.steps) : "0" }}</b>
					<span>record</span>
				</div>
				<div class="card stat-tile" aria-label="Moyenne de pas par jour">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V11M10 20V5M16 20v-7M21 20H3"/></svg>
					<b>{{ formatCount(history.stats.average) }}</b>
					<span>par jour</span>
				</div>
			</div>
			<p v-if="loading && !history" class="gallery-note note-self-centered">Chargement…</p>
			<div v-if="loadError" class="card stack-tight">
				<p class="form-error">{{ loadError }}</p>
				<button class="btn btn-ghost" type="button" :disabled="loading" @click="open(history?.month ?? null)">Réessayer</button>
			</div>
		</div>
		<BottomNav />
	</div>
</template>
