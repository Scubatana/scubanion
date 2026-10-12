export default defineNuxtConfig({
	compatibilityDate: "2026-01-01",
	ssr: false,
	devServer: {
		host: "0.0.0.0",
		port: 3000
	},
	css: ["~/assets/css/style.css", "~/assets/css/app.css"],
	runtimeConfig: {
		public: {
			apiUrl: process.env.NUXT_PUBLIC_API_URL || "http://localhost:8000"
		}
	},
	app: {
		head: {
			title: "Scubanion",
			viewport: "width=device-width, initial-scale=1, viewport-fit=cover"
		}
	}
});
