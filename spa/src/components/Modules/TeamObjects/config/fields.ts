import { DateField, TextField } from "quasar-ui-danx";
import { h } from "vue";

export const fields = [
	{
		name: "name",
		vnode: (props) => h(TextField, { ...props, maxLength: 255 }),
		label: "Name",
		required: true
	},
	{
		name: "description",
		vnode: (props) => h(TextField, { ...props, type: "textarea", inputClass: "h-56", maxLength: 64000 }),
		label: "Description"
	},
	{
		name: "date",
		vnode: (props) => h(DateField, { ...props }),
		label: "Date"
	},
	{
		name: "url",
		vnode: (props) => h(TextField, { ...props, maxLength: 2048 }),
		label: "URL"
	}
];
