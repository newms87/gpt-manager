import vue from "@vitejs/plugin-vue";
import { resolve } from "path";
import { ConfigEnv, defineConfig } from "vite";
// import VueDevTools from "vite-plugin-vue-devtools";
import svgLoader from "vite-svg-loader";

// https://vitejs.dev/config/
export default ({ command }: ConfigEnv) => {

	// For development w/ HMR, load the danx library + styles directly from the directory

	const danx = (command === "serve" ? {
		"quasar-ui-danx": "quasar-ui-danx/src",
		"quasar-ui-danx-styles": "quasar-ui-danx/src/styles/index.scss"
	} : {
		// Import from quasar-ui-danx module for production
		"quasar-ui-danx-styles": "quasar-ui-danx/dist/style.css"
	});
	console.log("Danx", command, danx);

	return defineConfig({
		plugins: [
			vue(),
			// VueDevTools(),
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
