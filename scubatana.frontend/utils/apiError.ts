export const UNREACHABLE_MESSAGE = "Impossible de joindre le serveur. Vérifie ta connexion et réessaie.";

const STATUS_MESSAGES: Record<number, string> = {
	401: "Ta session a expiré. Reconnecte-toi.",
	422: "Certaines informations sont incorrectes.",
	429: "Trop de tentatives. Réessaie dans quelques minutes."
};

const DEFAULT_MESSAGE = "Une erreur est survenue. Réessaie.";

export class ApiError extends Error {
	status: number;
	fields: Record<string, string>;

	constructor(status: number, message: string, fields: Record<string, string> = {}) {
		super(message);
		this.name = "ApiError";
		this.status = status;
		this.fields = fields;
	}
}

export function toApiError(status: number, data: unknown): ApiError {
	const errors = (data as { errors?: Record<string, string[]> } | null)?.errors ?? {};
	const fields: Record<string, string> = {};

	for (const [field, messages] of Object.entries(errors)) {
		if (Array.isArray(messages) && messages.length > 0) {
			fields[field] = String(messages[0]);
		}
	}

	return new ApiError(status, STATUS_MESSAGES[status] ?? DEFAULT_MESSAGE, fields);
}

export function readableMessage(error: unknown): string {
	return error instanceof ApiError ? error.message : DEFAULT_MESSAGE;
}
