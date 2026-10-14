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
	color: string;
	accessories: string[];
	unlocked_accessories?: string[];
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

export interface Streak {
	days: number;
	active: boolean;
}

export interface CompanionLookPayload {
	name: string;
	species: string;
	color: string;
	accessories: string[];
}

export interface CatalogPoint {
	x: number;
	y: number;
}

export interface CatalogAnchor extends CatalogPoint {
	width: number;
}

export interface CatalogCategory {
	id: string;
	label: string;
	origin: CatalogPoint;
}

export interface CatalogSpecies {
	id: string;
	label: string;
	svg: string;
	anchors: Record<string, CatalogAnchor>;
}

export interface CatalogColor {
	id: string;
	label: string;
	main: string;
	shade: string;
	light: string;
}

export interface CatalogAccessory {
	id: string;
	label: string;
	category: string;
	image: string;
	earnable?: boolean;
}

export interface CompanionCatalog {
	categories: CatalogCategory[];
	species: CatalogSpecies[];
	colors: CatalogColor[];
	accessories: CatalogAccessory[];
}

export interface ProfilePayload {
	pseudo: string;
	birth_date: string;
	gender: Gender | null;
	ambition: Ambition;
}

export type QuestAction = "auto" | "checkin" | "scan" | "answer" | "meet";

export type QuestStatus = "active" | "completed" | "failed" | "expired";

export interface QuestReward {
	xp: number;
	accessory: string | null;
}

export interface ClaimableReward extends QuestReward {
	id: number;
}

export interface QuestQuestion {
	question: string;
	choices: string[];
}

export interface QuestPlace {
	id: number;
	name: string;
	latitude: number;
	longitude: number;
	radius_m: number | null;
	outline: [number, number][] | null;
}

export interface QuestTask {
	id: number;
	type: string;
	action: QuestAction;
	description: string;
	target: number;
	value: number;
	completed: boolean;
	place: QuestPlace | null;
	place_type: { code: string; label: string } | null;
	questions: QuestQuestion[] | null;
	rewards: QuestReward[];
}

export interface PlayerQuest {
	id: number;
	quest_id: number;
	name: string;
	description: string | null;
	icon: string | null;
	period: string;
	expires_at: string | null;
	status: QuestStatus;
	step: number;
	steps: number;
	tasks: QuestTask[];
	step_rewards: QuestReward[];
	quest_rewards: QuestReward[];
	rewards_to_claim: ClaimableReward[];
}
