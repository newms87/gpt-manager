<template>
	<div class="p-6">
		<RenderedForm v-model:values="input" empty-value="" :form="agentForm" @update:values="$emit('change', $event)" />
	</div>
</template>
<script setup lang="ts">
import { RenderedForm, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

defineEmits(["change"]);

const input = computed(() => ({
	prompt: props.agent.prompt
}));

const agentForm: Form = {
	fields: [
		{
			name: "prompt",
			vnode: (props) => h(TextField, { ...props, type: "textarea" }),
			label: "Prompt"
		}
	]
};
</script>
