<template>
	<div class="p-6">
		<RenderedForm v-model:values="input" empty-value="" :form="agentForm" @update:values="$emit('change', $event)" />
	</div>
</template>
<script setup lang="ts">
import { AgentController } from "@/components/Agents/agentsControls";
import { NumberField, RenderedForm, SelectField, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { computed, h } from "vue";

interface Agent {
	name: string;
	model: string;
	temperature: string;
}

const props = defineProps<{
	agent: Agent,
}>();

defineEmits(["change"]);

const input = computed(() => ({
	name: props.agent.name,
	temperature: props.agent.temperature,
	model: props.agent.model
}));

const agentForm: Form = {
	fields: [
		{
			name: "name",
			component: TextField,
			label: "Agent Name",
			required: true
		},
		{
			name: "description",
			vnode: (props) => h(TextField, { ...props, type: "textarea" }),
			label: "Description"
		},
		{
			name: "model",
			vnode: (props) => h(SelectField, {
				...props,
				options: AgentController.getFieldOptions("models")
			}),
			label: "Model",
			required: true
		},
		{
			name: "temperature",
			component: NumberField,
			label: "Temperature",
			required: true
		}
	]
};
</script>
