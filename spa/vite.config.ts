import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import VueDevTools from 'vite-plugin-vue-devtools'


import { resolve } from "path";
import svgLoader from "vite-svg-loader";

// https://vitejs.dev/config/
export default ({ mode }) => {

    // For development w/ HMR, load the danx library + styles directly from the directory
    // NOTE: These are the paths relative to the mounted quasar-ui-danx directory inside the mva docker container
    const danx = (mode === "development" ? {
        "quasar-ui-danx": resolve(__dirname, "../quasar-ui-danx/src"),
        "quasar-ui-danx-styles": resolve(__dirname, "../quasar-ui-danx/src/styles/index.scss")
    } : {
        // Import from quasar-ui-danx module for production
        "quasar-ui-danx-styles": "quasar-ui-danx/dist/style.css"
    });

    console.log("Danx", mode, danx);

    return defineConfig({
        // base: mode === "production" ? "/build/" : "",
        // publicDir: "public",
        plugins: [vue(), VueDevTools(), svgLoader()],
        // server: {
        //     host: "0.0.0.0",
        //     port: 9090,
        //     hmr: {
        //         host: mode === "ci" ? "e2e-mva" : "localhost"
        //     }
        // },
        // preview: {
        //     host: "0.0.0.0",
        //     port: 9090
        // },
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url)),
                ...danx
            },
            extensions: [".mjs", ".js", ".ts", ".mts", ".jsx", ".tsx", ".json", ".vue"]
        },
        // build: {
        //     manifest: true,
        //     outDir: "public/build",
        //     rollupOptions: {
        //         input: "src/main.ts"
        //     }
        // }
    });
}
