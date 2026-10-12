const PUBLIC_PATHS = ["/login", "/verification"];

const PROFILE_PATH = "/profil";

export function resolveRedirect(path: string, hasToken: boolean, profileCompleted: boolean): string | null {
	const isPublic = PUBLIC_PATHS.includes(path);

	if (!hasToken) {
		return isPublic ? null : "/login";
	}

	if (!profileCompleted) {
		return path === PROFILE_PATH ? null : PROFILE_PATH;
	}

	return isPublic || path === PROFILE_PATH ? "/" : null;
}
