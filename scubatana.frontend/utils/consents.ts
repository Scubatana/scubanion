export interface PrivacyConsents {
	steps: boolean;
	location: boolean;
}

export function defaultConsents(): PrivacyConsents {
	return { steps: true, location: true };
}

export function parseConsents(raw: string | null): PrivacyConsents {
	if (raw === null) {
		return defaultConsents();
	}

	try {
		const parsed = JSON.parse(raw) as Partial<PrivacyConsents> | null;

		return {
			steps: typeof parsed?.steps === "boolean" ? parsed.steps : true,
			location: typeof parsed?.location === "boolean" ? parsed.location : true
		};
	} catch {
		return defaultConsents();
	}
}
