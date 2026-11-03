/**
 * Consentements RGPD explicites pour la captation des capteurs de l'appareil (pas et géolocalisation).
 *
 * Conformément au RGPD et au principe de minimisation (docs/cahier-des-charges.md § 6.5) :
 * - Les consentements sont strictement désactivés par défaut (opt-in obligatoire).
 * - Le consentement est rattaché à l'appareil local et au joueur (clé consents.<playerId>),
 *   car l'accès aux capteurs matériels est propre à chaque terminal physique.
 * - Tout consentement peut être révoqué à tout moment depuis les paramètres (/parametres).
 */
export interface PrivacyConsents {
	steps: boolean;
	location: boolean;
}

export function defaultConsents(): PrivacyConsents {
	return { steps: false, location: false };
}

export function parseConsents(raw: string | null): PrivacyConsents {
	if (raw === null) {
		return defaultConsents();
	}

	try {
		const parsed = JSON.parse(raw) as Partial<PrivacyConsents> | null;

		return {
			steps: typeof parsed?.steps === "boolean" ? parsed.steps : false,
			location: typeof parsed?.location === "boolean" ? parsed.location : false
		};
	} catch {
		return defaultConsents();
	}
}
