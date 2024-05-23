import "./assets/main.scss";
import { getAuthToken, isAuthenticated, setAuthToken } from "@/helpers/auth";
import { LocalStorage, Notify, Quasar } from "quasar";

import { applyCssVars, configure, FlashMessages, request } from "quasar-ui-danx";
import twColors from "tailwindcss/colors";

import { createApp } from "vue";
// eslint-disable-next-line import/extensions
import { colors } from "../tailwind.config";
import App from "./App.vue";
import router from "./router";

// Import styles
// See vite.config.mts for the alias to quasar-ui-danx-styles
// eslint-disable-next-line import/extensions
import "@/assets/main.scss";

// Inject all the CSS vars for our colors to override the Danx defaults
applyCssVars(colors, "tw-");

const baseUrl = import.meta.env.VITE_API_URL;
configure({
	request: {
		baseUrl: baseUrl,
		headers: {
			"X-App-Version": import.meta.env.VITE_APP_APP_VERSION,
			Authorization: isAuthenticated() ? `Bearer ${getAuthToken()}` : undefined
		},
		onUnauthorized: () => {
			setAuthToken("");
			FlashMessages.error("You have been logged out. Please log in again.");
			router.push({ name: "auth.login" });
		}
	},
	fileUpload: {
		directory: "stored-files",
		createPresignedUpload: (path, name, mime) => request.get(`${baseUrl}/file-upload/presigned-upload-url?path=${path}&name=${name}&mime=${mime}`),
		completePresignedUpload: (fileId) => request.post(`${baseUrl}/file-upload/presigned-upload-url-completed/${fileId}`)
	}
});

const app = createApp(App);
app.use(Quasar, {
	plugins: { Notify, LocalStorage },
	config: {
		brand: {
			primary: twColors.sky[800]
		}
	}
});

app.use(router);

app.mount("#app");

console.log(`GPT Manager is running at ${import.meta.env.VITE_API_URL} in mode ${import.meta.env.MODE}: version ${import.meta.env.VITE_APP_APP_VERSION}`);
