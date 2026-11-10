export interface BlockedPlayer {
	id: number;
	since: string;
	player: { id: number; pseudo: string };
}

export interface BlocksView {
	blocked: BlockedPlayer[];
}

export type ReportReason = "pseudo" | "behavior" | "other";

export const REPORT_REASONS: { value: ReportReason; label: string }[] = [
	{ value: "pseudo", label: "Pseudo déplacé" },
	{ value: "behavior", label: "Comportement gênant" },
	{ value: "other", label: "Autre" }
];

export const REPORT_COMMENT_MAX = 500;
