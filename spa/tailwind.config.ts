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
		"focus:outline-slate-600",
		"hover:bg-violet-950",
		"focus:bg-violet-950",
		"hover:outline-violet-950",
		"focus:outline-violet-950",
		"hover:text-violet-200",
		"focus:text-violet-200",
		"hover:text-violet-400",
		"focus:text-violet-400",
		"col-span-1",
		"col-span-2",
		"col-span-3",
		"col-span-4",
		"col-span-5",
		"col-span-6",
		"col-span-7",
		"col-span-8",
		"col-span-9",
		"col-span-10",
		"col-span-11",
		"col-span-12"
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

