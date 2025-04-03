import vue from "@vitejs/plugin-vue";
import fs from "fs";
import { resolve } from "path";
import { ConfigEnv, defineConfig } from "vite";
import VueDevTools from "vite-plugin-vue-devtools";
import svgLoader from "vite-svg-loader";

// Set to true to load the Vue Dev Tools (NOTE: This causes performance issues on some pages, so use as needed in dev)
const DEV_MODE = false;

console.log("Checking quasar-ui-danx existence:", fs.existsSync("./node_modules/quasar-ui-danx/src"));

// https://vitejs.dev/config/
export default ({ command }: ConfigEnv) => {

	// For development w/ HMR, load the danx library + styles directly from the directory

	const danx = (command === "serve" ? {
		"quasar-ui-danx": "quasar-ui-danx/index.ts",
		"quasar-ui-danx-styles": "quasar-ui-danx/src/styles/index.scss"
	} : {
		// Import from quasar-ui-danx module for production
		"quasar-ui-danx-styles": "quasar-ui-danx/dist/style.css"
	});
	console.log("Danx", command, danx);

	return defineConfig({
		plugins: [
			vue(),
			DEV_MODE && VueDevTools(),
			svgLoader()
		],
		build: {
			sourcemap: true
		},
		resolve: {
			alias: {
				"@": resolve(__dirname, "./src"),
				...danx
			},
			extensions: [".mjs", ".js", ".cjs", ".ts", ".mts", ".jsx", ".tsx", ".json", ".vue", "scss"]
		},
		server: {
			port: 5173
		}
	});
}
