import type { Ambition, Gender } from "./types";

export const AMBITION_LABELS: Record<Ambition, string> = {
	fitness: "Retrouver la forme",
	go_out: "Sortir plus souvent",
	meet_people: "Rencontrer du monde",
	explore: "Explorer mon quartier"
};

export const GENDER_LABELS: Record<Gender, string> = {
	female: "Femme",
	male: "Homme",
	other: "Autre"
};

export function formatCount(value: number): string {
	return new Intl.NumberFormat("fr-FR").format(value);
}

export function formatDay(isoDate: string): string {
	const [year, month, day] = isoDate.split("-").map(Number);
	const weekday = new Intl.DateTimeFormat("fr-FR", { weekday: "long" }).format(new Date(year, month - 1, day));

	return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${String(day).padStart(2, "0")}`;
}

export function latestBirthDate(today: Date, minimumAge: number): string {
	const year = today.getFullYear() - minimumAge;
	const month = String(today.getMonth() + 1).padStart(2, "0");
	const day = String(today.getDate()).padStart(2, "0");

	return `${year}-${month}-${day}`;
}
