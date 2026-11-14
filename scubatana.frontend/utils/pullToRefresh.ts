// Distance à tirer (en px, après résistance) pour lancer le rafraîchissement, et distance maximale affichée.
export const PULL_THRESHOLD = 64;
export const PULL_MAX = 96;

// Le doigt va plus loin que l'indicateur, comme sur Android : la moitié du geste, plafonnée.
export function pullOffset(deltaY: number): number {
	if (deltaY <= 0) {
		return 0;
	}

	return Math.min(PULL_MAX, deltaY / 2);
}

export function shouldRefresh(offset: number): boolean {
	return offset >= PULL_THRESHOLD;
}
