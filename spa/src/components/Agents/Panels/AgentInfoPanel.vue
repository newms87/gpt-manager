<template>
	<div class="p-6">
		<RenderedForm v-model:values="input" empty-value="" :form="agentForm" @update:values="$emit('change', $event)" />
	</div>
</template>
<script setup lang="ts">
import { AgentController } from "@/components/Agents/agentControls";
import { NumberField, RenderedForm, SelectField, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

defineEmits(["change"]);

const input = computed(() => ({
	name: props.agent.name,
	temperature: props.agent.temperature,
	model: props.agent.model,
	description: props.agent.description
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
