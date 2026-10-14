import { App } from "@capacitor/app";
import { currentPosition, positionAllowed } from "~/utils/geolocation";

const SEND_INTERVAL_MS = 60000;

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

export function usePosition() {
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

	function stop(): void {
		if (timer !== null) {
			clearInterval(timer);
			timer = null;
		}
	}

	async function start(): Promise<void> {
		if (timer !== null || !(await positionAllowed(false))) {
			return;
		}

		if (!listenersReady) {
			listenersReady = true;
			await App.addListener("pause", () => stop());
			await App.addListener("resume", () => void start());
		}

		timer = setInterval(() => void send(), SEND_INTERVAL_MS);
		await send();
	}

	return { start, stop, send };
}
