import { formatCount } from "./labels";
import type { CatalogAccessory, CompanionCatalog, Streak, Today } from "./types";

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
	today: Today | null;
	streak: Streak | null;
}

// Coups de patte du jour : envoyé par le joueur à cet ami, et reçu de lui.
export interface FriendCheer {
	sent: boolean;
	received: boolean;
}

export interface Friendship {
	id: number;
	since: string;
	player: FriendPlayer;
	cheer: FriendCheer;
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

export function friendGoalFill(today: Today | null): string {
	if (today === null || today.goal <= 0) {
		return "0%";
	}

	return `${Math.max(0, Math.min(100, Math.round((today.steps / today.goal) * 100)))}%`;
}

export function sortFriendsBySteps(friends: Friendship[]): Friendship[] {
	return [...friends].sort((first, second) => {
		const diff = (second.player.today?.steps ?? -1) - (first.player.today?.steps ?? -1);

		if (diff !== 0) {
			return diff;
		}

		return first.player.pseudo.localeCompare(second.player.pseudo, "fr");
	});
}

export function formatFriendSince(since: string): string {
	const [year, month, day] = since.slice(0, 10).split("-").map(Number);

	if (!year || !month || !day) {
		return "";
	}

	return new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "long", year: "numeric" }).format(new Date(year, month - 1, day));
}

// Espèce et accessoires portés, dans l'ordre du catalogue, pour la fiche d'un ami. Sans catalogue, rien à afficher.
export function friendCompanionDetails(catalog: CompanionCatalog | null, companion: FriendCompanion): { species: string | null; accessories: CatalogAccessory[] } {
	if (catalog === null) {
		return { species: null, accessories: [] };
	}

	return {
		species: catalog.species.find((entry) => entry.id === companion.species)?.label ?? null,
		accessories: catalog.accessories.filter((entry) => companion.accessories.includes(entry.id))
	};
}

// Nom du bouton qui ouvre la fiche : il remplace le contenu de la ligne pour un lecteur d'écran, il doit donc en reprendre l'essentiel.
export function friendRowLabel(friendship: Friendship): string {
	const { player, cheer } = friendship;
	const steps = player.today ? `${formatCount(player.today.steps)} pas sur ${formatCount(player.today.goal)}` : "pas masqués";
	const level = player.companion ? `niveau ${player.companion.level}, ` : "";
	const cheered = cheer.received && !cheer.sent ? ", t'a envoyé un coup de patte" : "";

	return `${player.pseudo}, ${level}${steps}${cheered}. Voir sa fiche`;
}

// Au-delà de ce nombre d'amis, la liste propose une recherche.
export const FRIEND_SEARCH_FROM = 8;

// Recherche par pseudo, sans tenir compte des majuscules ni des accents (« ines » trouve « Inès »).
export function filterFriends(friends: Friendship[], query: string): Friendship[] {
	const needle = foldForSearch(query.trim());

	if (needle === "") {
		return friends;
	}

	return friends.filter((friendship) => foldForSearch(friendship.player.pseudo).includes(needle));
}

function foldForSearch(text: string): string {
	return text.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLocaleLowerCase("fr");
}
