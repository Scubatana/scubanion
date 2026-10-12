import { resolveRedirect } from "~/utils/sessionRedirect";

export default defineNuxtRouteMiddleware(async (to) => {
	const session = useSession();

	await session.restore();

	const target = resolveRedirect(to.path, session.token.value !== null, session.profileCompleted.value);

	if (target !== null && target !== to.path) {
		return navigateTo(target, { replace: true });
	}
});
