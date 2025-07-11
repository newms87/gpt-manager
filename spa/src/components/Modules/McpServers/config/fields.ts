import { TextField } from "quasar-ui-danx";
import { h } from "vue";

export const fields = [
	{
		name: "name",
		vnode: (props) => h(TextField, { ...props, maxLength: 80 }),
		label: "Server Name",
		required: true
	},
	{
		name: "description",
		vnode: (props) => h(TextField, { ...props, type: "textarea", inputClass: "h-32", maxLength: 512 }),
		label: "Description"
	},
	{
		name: "server_url",
		vnode: (props) => h(TextField, { ...props }),
		label: "Server URL",
		required: true
	},
	{
		name: "headers",
		vnode: (props) => h(TextField, { 
			...props, 
			type: "textarea",
			inputClass: "h-32 font-mono text-sm",
			placeholder: '{"Authorization": "Bearer YOUR_TOKEN"}' 
		}),
		label: "Headers (JSON)"
	},
	{
		name: "allowed_tools",
		vnode: (props) => h(TextField, { 
			...props, 
			type: "textarea",
			inputClass: "h-32 font-mono text-sm",
			placeholder: '["tool1", "tool2"]' 
		}),
		label: "Allowed Tools (JSON Array)"
	}
];