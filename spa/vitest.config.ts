import vue from "@vitejs/plugin-vue";
import { resolve } from "path";
import { defineConfig } from "vitest/config";

export default defineConfig({
	plugins: [vue()],
	test: {
		environment: "happy-dom",
		include: ["src/**/*.{test,spec}.{js,ts}"],
		globals: true
	},
	resolve: {
		alias: {
			"@": resolve(__dirname, "./src")
		}
	}
});
