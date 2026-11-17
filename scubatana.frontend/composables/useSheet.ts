import { App } from "@capacitor/app";
import type { PluginListenerHandle } from "@capacitor/core";

// Feuilles ouvertes, la dernière en haut : le bouton retour d'Android et Échap ferment seulement celle-ci.
const openSheets: Array<() => void> = [];
let backButton: Promise<PluginListenerHandle> | null = null;

function closeTop(): void {
	openSheets.at(-1)?.();
}

function onKeydown(event: KeyboardEvent): void {
	if (event.key === "Escape") {
		closeTop();
	}
}

// Écouter le bouton retour remplace son comportement par défaut : on ne l'écoute donc que tant qu'une feuille est ouverte.
function listen(): void {
	window.addEventListener("keydown", onKeydown);
	backButton = App.addListener("backButton", closeTop);
}

function stopListening(): void {
	window.removeEventListener("keydown", onKeydown);
	backButton?.then((handle) => handle.remove()).catch(() => undefined);
	backButton = null;
}

export function useSheet(close: () => void): void {
	onMounted(() => {
		openSheets.push(close);

		if (openSheets.length === 1) {
			listen();
		}
	});

	onBeforeUnmount(() => {
		const index = openSheets.lastIndexOf(close);

		if (index !== -1) {
			openSheets.splice(index, 1);
		}

		if (openSheets.length === 0) {
			stopListening();
		}
	});
}
