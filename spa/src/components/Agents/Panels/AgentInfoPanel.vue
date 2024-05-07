<template>
	<div class="p-6">
		<RenderedForm v-model:values="input" empty-value="" :form="agentForm" @update:values="onChange" />
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Agents/agentActions";
import { AgentController } from "@/components/Agents/agentControls";
import { Agent } from "@/components/Agents/agents";
import { NumberField, RenderedForm, SelectField, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	name: props.agent.name,
	temperature: props.agent.temperature,
	model: props.agent.model,
	description: props.agent.description
});

function onChange(input) {
	updateAction.trigger(props.agent, input);
}

const agentForm: Form = {
	fields: [
		{
			name: "name",
			vnode: (props) => h(TextField, { ...props, maxLength: 40 }),
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
			vnode: (props) => h(NumberField, { ...props }),
			label: "Temperature",
			required: true
		}
	]
};
</script>
