<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="agent"
			:form="agentForm"
		/>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Agents/agentActions";
import { Agent } from "@/types/agents";
import { ActionForm, Form } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	agent: Agent,
}>();

const updateDebouncedAction = getAction("update-debounced");

const agentForm: Form = {
	fields: [
		{
			name: "prompt",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				maxLength: 100000
			}),
			label: "Prompt"
		}
	]
};
</script>
