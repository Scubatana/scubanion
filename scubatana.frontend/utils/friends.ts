import type { Streak, Today } from "./types";

export interface FriendCompanion {
	name: string;
	species: string;
	color: string;
	accessories: string[];
	level: number;
}

export interface FriendPlayer {
	id: number;
	pseudo: string;
	companion: FriendCompanion | null;
	today: Today;
	streak: Streak;
}

export interface Friendship {
	id: number;
	since: string;
	player: FriendPlayer;
}

export interface FriendInvitation {
	id: number;
	sent_at: string;
	player: {
		id: number;
		pseudo: string;
	};
}

export interface FriendsView {
	code: string;
	friends: Friendship[];
	received: FriendInvitation[];
	sent: FriendInvitation[];
}

export interface FriendQrToken {
	token: string;
	expires_at: string;
}

/**
 * Délai avant de redemander un QR : 10 secondes avant son expiration, pour qu'un ami
 * ne scanne jamais un QR périmé, mais jamais moins de 5 secondes entre deux demandes.
 */
export function qrRefreshDelay(expiresAt: string, now: number = Date.now()): number {
	const remaining = new Date(expiresAt).getTime() - now;

	if (Number.isNaN(remaining)) {
		return 5_000;
	}

	return Math.max(5_000, remaining - 10_000);
}

export function normalizeFriendCodeInput(input: string): string {
	return input.replace(/[\s-]+/g, "").toUpperCase();
}

export function formatFriendCode(code: string): string {
	const clean = normalizeFriendCodeInput(code);

	if (clean.length !== 8) {
		return clean;
	}

	return `${clean.slice(0, 4)}-${clean.slice(4)}`;
}

export function friendGoalFill(today: Today): string {
	if (today.goal <= 0) {
		return "0%";
	}

	return `${Math.max(0, Math.min(100, Math.round((today.steps / today.goal) * 100)))}%`;
}

export function sortFriendsBySteps(friends: Friendship[]): Friendship[] {
	return [...friends].sort((first, second) => {
		const diff = second.player.today.steps - first.player.today.steps;

		if (diff !== 0) {
			return diff;
		}

		return first.player.pseudo.localeCompare(second.player.pseudo, "fr");
	});
}
