export type Gender = "female" | "male" | "other";

export type Ambition = "fitness" | "go_out" | "meet_people" | "explore";

export interface PlayerStats {
	total_steps: number;
	quests_done: number;
	friends_count: number;
}

export interface Player {
	id: number;
	email: string;
	pseudo: string | null;
	birth_date: string | null;
	gender: Gender | null;
	ambition: Ambition | null;
	profile_completed: boolean;
	stats: PlayerStats;
}

export interface Companion {
	name: string;
	species: string;
	level: number;
	xp: number;
	xp_for_next_level: number;
	mood: number;
	health: number;
}

export interface Today {
	date: string;
	steps: number;
	goal: number;
}

export interface ProfilePayload {
	pseudo: string;
	birth_date: string;
	gender: Gender | null;
	ambition: Ambition;
}
