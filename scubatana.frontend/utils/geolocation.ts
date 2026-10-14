import { Capacitor, registerPlugin } from "@capacitor/core";

export type PositionPermission = "granted" | "denied" | "prompt" | "prompt-with-rationale";

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

export async function positionAllowed(askIfNeeded: boolean): Promise<boolean> {
	if (!Capacitor.isNativePlatform()) {
		return false;
	}

	const current = (await Position.checkPermissions()).location;

	if (current === "granted" || !askIfNeeded) {
		return current === "granted";
	}

	return (await Position.requestPermissions()).location === "granted";
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
