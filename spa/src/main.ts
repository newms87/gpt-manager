import "./assets/main.scss";
import { LocalStorage, Notify, Quasar } from "quasar";

import { applyCssVars, configure } from "quasar-ui-danx";
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
		baseUrl: baseUrl
	},
	fileUpload: {
		directory: "stored-files",
		presignedUploadUrl: (path, name, mime) => `${baseUrl}/file-upload/presigned-upload-url?path=${path}&name=${name}&mime=${mime}`,
		uploadCompletedUrl: (fileId) => `${baseUrl}/file-upload/presigned-upload-url-completed/${fileId}`
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
