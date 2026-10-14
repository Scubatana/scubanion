import { Capacitor, registerPlugin } from "@capacitor/core";

export type PositionPermission = "granted" | "denied" | "prompt" | "prompt-with-rationale";

export type PositionAccess = "granted" | "denied" | "unsupported";

export interface CurrentPosition {
	latitude: number;
	longitude: number;
	accuracy_m: number;
	mocked: boolean;
	recorded_at: string;
}

interface NativeFix {
	latitude: number;
	longitude: number;
	accuracy: number;
	mocked: boolean;
	time: number;
}

interface PositionPlugin {
	checkPermissions(): Promise<{ location: PositionPermission }>;
	requestPermissions(): Promise<{ location: PositionPermission }>;
	current(): Promise<NativeFix>;
	openSettings(): Promise<void>;
}

const Position = registerPlugin<PositionPlugin>("Position");

export const LOCATION_REQUIRED = "Active la localisation pour valider ce lieu.";

export const POSITION_UNAVAILABLE = "Position introuvable pour le moment, réessaie dans un instant.";

export function toCurrentPosition(fix: NativeFix): CurrentPosition {
	return {
		latitude: fix.latitude,
		longitude: fix.longitude,
		accuracy_m: Math.round(fix.accuracy),
		mocked: fix.mocked,
		recorded_at: new Date(fix.time).toISOString()
	};
}

export async function positionAccess(askIfNeeded: boolean): Promise<PositionAccess> {
	if (!Capacitor.isNativePlatform()) {
		return "unsupported";
	}

	let current = (await Position.checkPermissions()).location;

	if (current !== "granted" && askIfNeeded) {
		current = (await Position.requestPermissions()).location;
	}

	return current === "granted" ? "granted" : "denied";
}

export async function positionAllowed(askIfNeeded: boolean): Promise<boolean> {
	return (await positionAccess(askIfNeeded)) === "granted";
}

export async function openLocationSettings(): Promise<void> {
	await Position.openSettings();
}

export async function currentPosition(askIfNeeded: boolean): Promise<CurrentPosition> {
	if (!(await positionAllowed(askIfNeeded))) {
		throw new Error(LOCATION_REQUIRED);
	}

	try {
		return toCurrentPosition(await Position.current());
	} catch {
		throw new Error(POSITION_UNAVAILABLE);
	}
}
