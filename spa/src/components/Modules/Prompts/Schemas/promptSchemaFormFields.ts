import { TextField } from "quasar-ui-danx";
import { h } from "vue";

export const fields = [
	{
		name: "name",
		vnode: (props) => h(TextField, { ...props, maxLength: 100 }),
		label: "Agent Name",
		required: true
	},
	{
		name: "description",
		vnode: (props) => h(TextField, { ...props, type: "textarea", inputClass: "h-56", maxLength: 512 }),
		label: "Description"
	}
];
