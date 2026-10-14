import { App } from "@capacitor/app";
import { ApiError } from "~/utils/apiError";
import { Preferences } from "@capacitor/preferences";
import { openStepSettings, readRecordedSteps, stepPermissionGranted, stepSensorAvailable, watchSteps } from "~/utils/stepCounter";
import { applyReading, applyRecorded, emptyLedger, localDay, markSynced, parseLedger, pendingEntries, recordedFrom, resume, suspend, unsyncedSteps, type StepLedger } from "~/utils/stepLedger";
import type { Companion, Streak, Today } from "~/utils/types";

export type StepStatus = "idle" | "unavailable" | "denied" | "tracking";

interface SyncResponse {
	today: Today;
	streak: Streak;
	total_steps: number;
	companion?: Companion | null;
}

const DEVICE_KEY = "steps.device";

const LEDGER_KEY_PREFIX = "steps.ledger.";

const SYNC_INTERVAL_MS = 20000;

let ledger: StepLedger = emptyLedger();
let ledgerKey: string | null = null;
let stopWatching: (() => Promise<void>) | null = null;
let appListenersReady = false;
let lastSyncAt = 0;
let syncInFlight: Promise<void> | null = null;
let catchingUp: Promise<void> | null = null;

export function useSteps() {
	const status = useState<StepStatus>("steps-status", () => "idle");
	const unsyncedToday = useState<number>("steps-unsynced-today", () => 0);
	const { player, companion, today, streak } = usePlayer();

	const displayedSteps = computed(() => (today.value?.steps ?? 0) + unsyncedToday.value);

	async function deviceId(): Promise<string> {
		const stored = (await Preferences.get({ key: DEVICE_KEY })).value;

		if (stored) {
			return stored;
		}

		const created = crypto.randomUUID();
		await Preferences.set({ key: DEVICE_KEY, value: created });

		return created;
	}

	async function persist(): Promise<void> {
		if (ledgerKey !== null) {
			await Preferences.set({ key: ledgerKey, value: JSON.stringify(ledger) });
		}

		unsyncedToday.value = unsyncedSteps(ledger, localDay(new Date()));
	}

	async function halt(): Promise<void> {
		if (status.value === "tracking") {
			ledger = suspend(ledger);
			await persist();
		}

		await stopWatching?.().catch(() => undefined);
		stopWatching = null;
		ledger = emptyLedger();
		ledgerKey = null;
		lastSyncAt = 0;
		status.value = "idle";
		unsyncedToday.value = 0;
	}

	async function send(entries: ReturnType<typeof pendingEntries>): Promise<void> {
		lastSyncAt = Date.now();

		try {
			const response = await useApi().post<SyncResponse>("/me/steps", { device_id: await deviceId(), days: entries });

			ledger = markSynced(ledger, entries);
			today.value = response.today;
			streak.value = response.streak ?? streak.value;
			companion.value = response.companion ?? companion.value;
			await persist();

			if (player.value) {
				player.value = { ...player.value, stats: { ...player.value.stats, total_steps: response.total_steps } };
			}
		} catch (error) {
			lastSyncAt = Date.now();

			if (error instanceof ApiError && error.status === 401) {
				await halt();
			}
		}
	}

	async function sync(): Promise<void> {
		while (syncInFlight !== null) {
			await syncInFlight;
		}

		const entries = pendingEntries(ledger, localDay(new Date()));

		if (ledgerKey === null || entries.length === 0) {
			return;
		}

		syncInFlight = send(entries).finally(() => {
			syncInFlight = null;
		});
		await syncInFlight;
	}

	async function onCount(count: number): Promise<void> {
		ledger = applyReading(ledger, count, localDay(new Date()));
		await persist();

		if (Date.now() - lastSyncAt >= SYNC_INTERVAL_MS) {
			await sync();
		}
	}

	async function readAndSync(): Promise<void> {
		try {
			ledger = applyRecorded(ledger, await readRecordedSteps(recordedFrom(ledger, new Date())));
			await persist();
		} catch {
			await persist();
		}

		await sync();
	}

	function catchUp(): Promise<void> {
		catchingUp ??= readAndSync().finally(() => {
			catchingUp = null;
		});

		return catchingUp;
	}

	async function settled(): Promise<void> {
		await catchingUp;
	}

	async function onResume(): Promise<void> {
		if (status.value === "tracking") {
			await catchUp();
		} else if (status.value === "denied") {
			await start(false);
		}
	}

	async function start(askPermission: boolean): Promise<void> {
		if (status.value === "tracking" || player.value === null) {
			return;
		}

		if (!(await stepSensorAvailable())) {
			status.value = "unavailable";
			return;
		}

		if (!appListenersReady) {
			appListenersReady = true;
			await App.addListener("pause", () => void sync());
			await App.addListener("resume", () => void onResume());
		}

		if (!(await stepPermissionGranted(askPermission))) {
			status.value = "denied";
			return;
		}

		ledgerKey = `${LEDGER_KEY_PREFIX}${player.value.id}`;
		ledger = resume(parseLedger((await Preferences.get({ key: ledgerKey })).value), Date.now());
		await persist();
		stopWatching = await watchSteps((count) => void onCount(count));
		status.value = "tracking";
		await catchUp();
	}

	async function reset(): Promise<void> {
		if (status.value === "tracking") {
			await sync();
		}

		await halt();
	}

	async function openSettings(): Promise<void> {
		await openStepSettings();
	}

	return { status, displayedSteps, start, sync, settled, reset, openSettings };
}
