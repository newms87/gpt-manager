import { AgentController } from "@/components/Modules/Agents/agentControls";
import { SelectField, SliderNumberField, TextField } from "quasar-ui-danx";
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
	},
	{
		name: "model",
		vnode: (props) => h(SelectField, {
			...props,
			options: AgentController.getFieldOptions("aiModels")
		}),
		label: "Model",
		required: true
	},
	{
		name: "temperature",
		vnode: (props) => h(SliderNumberField, {
			...props,
			min: 0,
			max: 2,
			step: .1,
			dark: true
		}),
		label: "Temperature",
		required: true
	}
];
