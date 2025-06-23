import { SelectField, TextField } from "quasar-ui-danx";
import { h } from "vue";

export const connectionFields = [
	{
		name: "name",
		label: "Connection Name",
		vnode: (props) => h(TextField, { ...props, maxLength: 100 }),
		required: true
	},
	{
		name: "phone_number", 
		label: "Phone Number",
		vnode: (props) => h(TextField, { ...props, placeholder: "+1234567890" }),
		required: true
	},
	{
		name: "api_provider",
		label: "API Provider",
		vnode: (props) => h(SelectField, {
			...props,
			options: [
				{ label: "Twilio", value: "twilio" },
				{ label: "WhatsApp Business", value: "whatsapp_business" }
			]
		}),
		required: true
	},
	{
		name: "account_sid",
		label: "Account SID",
		vnode: (props) => h(TextField, props),
		vIf: (item) => item?.api_provider === 'twilio'
	},
	{
		name: "auth_token",
		label: "Auth Token", 
		vnode: (props) => h(TextField, { ...props, type: "password" }),
		vIf: (item) => item?.api_provider === 'twilio'
	},
	{
		name: "access_token",
		label: "Access Token",
		vnode: (props) => h(TextField, { ...props, type: "password" }),
		vIf: (item) => item?.api_provider === 'whatsapp_business'
	}
];

export const messageFields = [];