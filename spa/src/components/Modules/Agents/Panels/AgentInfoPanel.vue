<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="agentForm"
			:saving="updateAction.isApplying"
			@update:values="updateAction.trigger(agent, input)"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import { Agent } from "@/types/agents";
import { RenderedForm, SelectField, SliderNumberField, TextField } from "quasar-ui-danx";
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
	]
};
</script>
