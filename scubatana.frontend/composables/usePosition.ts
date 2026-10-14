import { App } from "@capacitor/app";
import { currentPosition, openLocationSettings, positionAccess } from "~/utils/geolocation";

const SEND_INTERVAL_MS = 60000;

export type PositionStatus = "idle" | "tracking" | "denied" | "unsupported";

let timer: ReturnType<typeof setInterval> | null = null;
let listenersReady = false;

async function refreshQuests(): Promise<void> {
	const quests = useQuests();

	if (quests.quests.value.length > 0) {
		await quests.load();
	} else {
		await quests.flush();
	}
}

function clearTimer(): void {
	if (timer !== null) {
		clearInterval(timer);
		timer = null;
	}
}

export function usePosition() {
	const status = useState<PositionStatus>("position.status", () => "idle");
	const asked = useState("position.asked", () => false);
	const promptDismissed = useState("position.prompt-dismissed", () => false);
	const showPrompt = computed(() => status.value === "denied" && !promptDismissed.value);

	async function send(): Promise<void> {
		if (useSession().token.value === null) {
			return;
		}

		try {
			await useApi().post<void>("/me/position", { ...(await currentPosition(false)) });
			await refreshQuests();
		} catch {
			return;
		}
	}

	async function listen(): Promise<void> {
		if (listenersReady) {
			return;
		}

		listenersReady = true;
		await App.addListener("pause", () => clearTimer());
		await App.addListener("resume", () => {
			const position = usePosition();

			if (position.status.value === "tracking" || position.status.value === "denied") {
				void position.start(false);
			}
		});
	}

	async function start(askPermission: boolean): Promise<void> {
		if (timer !== null) {
			return;
		}

		await listen();
		const ask = askPermission && !asked.value;
		asked.value = asked.value || ask;
		const access = await positionAccess(ask);
		status.value = access === "granted" ? "tracking" : access;

		if (access !== "granted" || timer !== null) {
			return;
		}

		timer = setInterval(() => void send(), SEND_INTERVAL_MS);
		await send();
	}

	function stop(): void {
		clearTimer();
		status.value = "idle";
	}

	function dismissPrompt(): void {
		promptDismissed.value = true;
	}

	async function openSettings(): Promise<void> {
		await openLocationSettings();
	}

	return { status, showPrompt, start, stop, send, dismissPrompt, openSettings };
}
