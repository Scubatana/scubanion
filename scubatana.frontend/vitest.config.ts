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
			include: ["composables/**/*.ts", "utils/**/*.ts"]
		}
	}
});
