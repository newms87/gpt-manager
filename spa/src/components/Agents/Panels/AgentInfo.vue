<template>
	<div class="p-6">
		<RenderedForm v-model:values="input" empty-value="" :form="agentForm" @update:values="$emit('change', $event)" />
	</div>
</template>
<script setup lang="ts">
import { RenderedForm, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { shallowRef } from "vue";

interface Agent {
	name: string;
	model: string;
	temperature: string;
}

const props = defineProps<{
	agent: Agent,
}>();

defineEmits(["change"]);

const input = shallowRef({
	name: props.agent.name,
	temperature: props.agent.temperature,
	model: props.agent.model
});
const agentForm: Form = {
	fields: [
		{
			name: "name",
			component: TextField,
			label: "Agent Name",
			required: true
		}
	]
};
</script>
