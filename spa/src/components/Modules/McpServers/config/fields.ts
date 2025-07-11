import { BooleanField, SelectField, TextField } from "quasar-ui-danx";
import { h } from "vue";

export const fields = [
	{
		name: "name",
		vnode: (props) => h(TextField, { ...props, maxLength: 80 }),
		label: "Server Name",
		required: true
	},
	{
		name: "label",
		vnode: (props) => h(TextField, { ...props, maxLength: 80 }),
		label: "Server Label",
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
	},
	{
		name: "require_approval",
		vnode: (props) => h(SelectField, {
			...props,
			options: [
				{ value: "never", label: "Never" },
				{ value: "always", label: "Always" }
			]
		}),
		label: "Require Approval",
		required: true
	},
	{
		name: "is_active",
		vnode: (props) => h(BooleanField, props),
		label: "Active"
	}
];