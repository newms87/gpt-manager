import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { ConfigEnv, defineConfig } from 'vite'
import VueDevTools from 'vite-plugin-vue-devtools'
import svgLoader from 'vite-svg-loader'

// https://vitejs.dev/config/
export default ({ command }: ConfigEnv) => {

    // For development w/ HMR, load the danx library + styles directly from the directory

    const danx = (command === 'serve' ? {
        'quasar-ui-danx': resolve(__dirname, '../../quasar-ui-danx/ui/src'),
        'quasar-ui-danx-styles': resolve(__dirname, '../../quasar-ui-danx/ui/src/styles/index.scss')
    } : {
        // Import from quasar-ui-danx module for production
        'quasar-ui-danx-styles': 'quasar-ui-danx/dist/style.css'
    })
    console.log('Danx', command, danx)

    return defineConfig({
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
                '@': resolve(__dirname, './src'),
                ...danx
            },
            extensions: ['.mjs', '.js', '.cjs', '.ts', '.mts', '.jsx', '.tsx', '.json', '.vue', 'scss']
        }
        // build: {
        //     manifest: true,
        //     outDir: "public/build",
        //     rollupOptions: {
        //         input: "src/main.ts"
        //     }
        // }
    })
}
