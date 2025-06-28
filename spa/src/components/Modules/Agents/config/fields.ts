import { NumberField, SelectField, SliderNumberField, TextField } from "quasar-ui-danx";
import { h } from "vue";
import { controls } from "./controls";

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
	},
	{
		name: "model",
		vnode: (props) => h(SelectField, {
			...props,
			options: controls.getFieldOptions("aiModels")
		}),
		label: "Model",
		required: true
	},
	{
		name: "retry_count",
		label: "Valid response retries?",
		vnode: (props) => h(NumberField, { ...props, class: "w-40" })
	}
];
