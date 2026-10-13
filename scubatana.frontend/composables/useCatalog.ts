import type { CompanionCatalog } from "~/utils/types";

export function useCatalog() {
	const catalog = useState<CompanionCatalog | null>("companion-catalog", () => null);

	async function ensureLoaded(): Promise<void> {
		if (catalog.value !== null) {
			return;
		}

		catalog.value = await useApi().get<CompanionCatalog>("/companion/catalog");
	}

	function reset(): void {
		catalog.value = null;
	}

	return { catalog, ensureLoaded, reset };
}
