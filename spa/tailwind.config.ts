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
		"../../quasar-ui-danx/ui/src/**/*.{html,js,vue,ts}"
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

