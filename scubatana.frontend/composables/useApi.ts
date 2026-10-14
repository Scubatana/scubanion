import { ApiError, toApiError, UNREACHABLE_MESSAGE } from "~/utils/apiError";
import { httpRequest, type HttpResponse } from "~/utils/http";

export function useApi() {
	const baseUrl = String(useRuntimeConfig().public.apiUrl).replace(/\/+$/, "");
	const session = useSession();

	async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
		const headers: Record<string, string> = { Accept: "application/json", "Content-Type": "application/json" };

		if (session.token.value) {
			headers.Authorization = `Bearer ${session.token.value}`;
		}

		let response: HttpResponse;

		try {
			response = await httpRequest(method, `${baseUrl}/api${path}`, headers, body);
		} catch {
			throw new ApiError(0, UNREACHABLE_MESSAGE);
		}

		if (response.status === 401) {
			await session.clear();
			await navigateTo("/login");
		}

		if (response.status < 200 || response.status >= 300) {
			throw toApiError(response.status, response.data);
		}

		return response.data as T;
	}

	function get<T>(path: string): Promise<T> {
		return request<T>("GET", path);
	}

	function post<T>(path: string, body?: unknown): Promise<T> {
		return request<T>("POST", path, body);
	}

	function put<T>(path: string, body: unknown): Promise<T> {
		return request<T>("PUT", path, body);
	}

	function del<T>(path: string): Promise<T> {
		return request<T>("DELETE", path);
	}

	return { get, post, put, del };
}
