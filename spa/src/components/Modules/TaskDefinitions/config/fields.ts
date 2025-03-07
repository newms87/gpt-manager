import { TextField } from "quasar-ui-danx";
import { h } from "vue";

export const fields = [
	{
		name: "name",
		vnode: (props) => h(TextField, { ...props, maxLength: 100 }),
		label: "Name",
		required: true
	},
	{
		name: "description",
		vnode: (props) => h(TextField, { ...props, type: "textarea", inputClass: "h-16", maxLength: 512 }),
		label: "Description"
	}
];
