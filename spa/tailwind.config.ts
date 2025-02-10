/** @type {import("tailwindcss").Config} */
export const colors = {
	"slate": {
		"450": "rgb(143,153,174)"
	}
};
import typography from "@tailwindcss/typography";

export default {
	content: [
		"./src/**/*.{html,js,vue,ts}",
		"quasar-ui-danx/src/**/*.{html,js,vue,ts}",
		"../../quasar-ui-danx/ui/src/**/*.{html,js,vue,ts}"
	],
	safelist: [
		"hover:bg-slate-600",
		"focus:bg-slate-600",
		"hover:outline-slate-600",
		"focus:outline-slate-600"
	],
	theme: {
		extend: {
			colors
		}
	},
	plugins: [
		typography()
	]
};

