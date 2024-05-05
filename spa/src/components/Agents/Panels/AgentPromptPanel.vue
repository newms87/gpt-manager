<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="agentForm"
			@update:values="$emit('change', $event)"
		/>
	</div>
</template>
<script setup lang="ts">
import { Agent } from "@/components/Agents/agents";
import { RenderedForm, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

defineEmits(["change"]);

const input = ref({ prompt: props.agent.prompt });

const agentForm: Form = {
	fields: [
		{
			name: "prompt",
			vnode: (props) => h(TextField, { ...props, inputClass: "h-[80vh]", type: "textarea" }),
			label: "Prompt"
		}
	]
};
</script>
