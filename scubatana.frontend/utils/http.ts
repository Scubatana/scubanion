import { Capacitor, CapacitorHttp } from "@capacitor/core";

export interface HttpResponse {
	status: number;
	data: unknown;
}

export function parseBody(body: unknown): unknown {
	if (typeof body !== "string") {
		return body ?? null;
	}

	if (body === "") {
		return null;
	}

	try {
		return JSON.parse(body);
	} catch {
		return null;
	}
}

export async function httpRequest(method: string, url: string, headers: Record<string, string>, body?: unknown): Promise<HttpResponse> {
	if (Capacitor.isNativePlatform()) {
		const response = await CapacitorHttp.request({ method, url, headers, data: body });

		return { status: response.status, data: parseBody(response.data) };
	}

	const response = await fetch(url, {
		method,
		headers,
		body: body === undefined ? undefined : JSON.stringify(body)
	});

	return { status: response.status, data: parseBody(await response.text()) };
}
