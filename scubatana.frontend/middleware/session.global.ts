import { resolveRedirect } from "~/utils/sessionRedirect";

export default defineNuxtRouteMiddleware(async (to) => {
	const session = useSession();
	const auth = useAuth();

	await session.restore();

	if (session.token.value === null) {
		await auth.restorePendingEmail();
	}

	const target = resolveRedirect(to.path, session.token.value !== null, session.profileCompleted.value, auth.pendingEmail.value !== null);

	if (target !== null && target !== to.path) {
		return navigateTo(target, { replace: true });
	}
});
