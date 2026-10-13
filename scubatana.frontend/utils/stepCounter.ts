import { Capacitor, registerPlugin, type PluginListenerHandle } from "@capacitor/core";
import type { RecordedBucket } from "./stepLedger";

export type StepPermissionState = "granted" | "denied" | "prompt" | "prompt-with-rationale";

interface StepCounterPlugin {
	isAvailable(): Promise<{ available: boolean }>;
	checkPermissions(): Promise<{ activity: StepPermissionState }>;
	requestPermissions(): Promise<{ activity: StepPermissionState }>;
	start(): Promise<void>;
	stop(): Promise<void>;
	startRecording(): Promise<void>;
	readRecorded(options: { from: number }): Promise<{ buckets: RecordedBucket[] }>;
	openSettings(): Promise<void>;
	addListener(eventName: "steps", listener: (event: { count: number }) => void): Promise<PluginListenerHandle>;
}

const StepCounter = registerPlugin<StepCounterPlugin>("StepCounter");

export async function stepSensorAvailable(): Promise<boolean> {
	if (!Capacitor.isNativePlatform()) {
		return false;
	}

	return (await StepCounter.isAvailable()).available;
}

export async function stepPermissionGranted(askIfNeeded: boolean): Promise<boolean> {
	const current = (await StepCounter.checkPermissions()).activity;

	if (current === "granted" || !askIfNeeded) {
		return current === "granted";
	}

	return (await StepCounter.requestPermissions()).activity === "granted";
}

export async function watchSteps(onCount: (count: number) => void): Promise<() => Promise<void>> {
	const handle = await StepCounter.addListener("steps", (event) => onCount(event.count));

	await StepCounter.start();

	return async () => {
		await handle.remove();
		await StepCounter.stop();
	};
}

export async function readRecordedSteps(from: number): Promise<RecordedBucket[]> {
	await StepCounter.startRecording();

	return (await StepCounter.readRecorded({ from })).buckets;
}

export async function openStepSettings(): Promise<void> {
	await StepCounter.openSettings();
}
