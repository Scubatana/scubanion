import { defineVitestConfig } from "@nuxt/test-utils/config";

export default defineVitestConfig({
	test: {
		environment: "nuxt",
		include: ["tests/**/*.test.ts"],
		coverage: {
			provider: "v8",
			// Chemins de lcov.info relatifs à la racine du dépôt, d'où SonarQube les résout.
			reporter: ["text", ["lcov", { projectRoot: ".." }]],
			reportsDirectory: "coverage",
			// Les composants sont des sources de SonarQube : absents de lcov.info, ils y comptent pour 0 %.
			// Les pages aussi, mais v8 ne leur rattache aucune ligne (LF:0) même quand elles sont testées : à creuser.
			include: ["components/**/*.vue", "composables/**/*.ts", "utils/**/*.ts"]
		}
	}
});
