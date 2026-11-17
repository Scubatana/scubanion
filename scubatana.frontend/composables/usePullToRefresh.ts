import type { Ref } from "vue";
import { pullOffset, shouldRefresh } from "~/utils/pullToRefresh";

// Tirer la liste vers le bas, quand elle est déjà tout en haut, relance onRefresh.
export function usePullToRefresh(container: Ref<HTMLElement | null>, onRefresh: () => Promise<void>) {
	const offset = ref(0);
	const refreshing = ref(false);
	let startY: number | null = null;

	function onTouchStart(event: TouchEvent): void {
		const element = container.value;

		startY = element !== null && element.scrollTop <= 0 && !refreshing.value ? event.touches[0]?.clientY ?? null : null;
	}

	function onTouchMove(event: TouchEvent): void {
		if (startY === null) {
			return;
		}

		offset.value = pullOffset((event.touches[0]?.clientY ?? startY) - startY);
	}

	async function onTouchEnd(): Promise<void> {
		if (startY === null) {
			return;
		}

		startY = null;

		if (!shouldRefresh(offset.value)) {
			offset.value = 0;
			return;
		}

		refreshing.value = true;

		try {
			await onRefresh();
		} finally {
			refreshing.value = false;
			offset.value = 0;
		}
	}

	onMounted(() => {
		const element = container.value;

		element?.addEventListener("touchstart", onTouchStart, { passive: true });
		element?.addEventListener("touchmove", onTouchMove, { passive: true });
		element?.addEventListener("touchend", onTouchEnd);
		element?.addEventListener("touchcancel", onTouchEnd);
	});

	onBeforeUnmount(() => {
		const element = container.value;

		element?.removeEventListener("touchstart", onTouchStart);
		element?.removeEventListener("touchmove", onTouchMove);
		element?.removeEventListener("touchend", onTouchEnd);
		element?.removeEventListener("touchcancel", onTouchEnd);
	});

	return { offset, refreshing };
}
