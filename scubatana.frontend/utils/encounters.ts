export interface EncounterResult {
	player: { pseudo: string };
	met_at: string;
	new: boolean;
}

export function encounterMessage(result: EncounterResult): string {
	return result.new
		? `Rencontre avec ${result.player.pseudo} validée !`
		: `Ta rencontre avec ${result.player.pseudo} est déjà comptée aujourd'hui.`;
}
