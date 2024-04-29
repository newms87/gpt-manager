<template>
	<div class="p-6">
		<RenderedForm :form="agentForm" :values="agent" @update:values="onUpdate" />
	</div>
</template>
<script setup lang="ts">
import { RenderedForm, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";

interface Agent {
	name: string;
	model: string;
	temperature: string;
}

defineProps<{
	agent: Agent,
}>();

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

function onUpdate(values: Agent) {
	console.log("Updated values", values);
}
</script>
