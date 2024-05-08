<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="agentForm"
			@update:values="updateDebouncedAction.trigger(agent, $event)"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Agents/agentActions";
import { Agent } from "@/components/Agents/agents";
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { RenderedForm } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const updateDebouncedAction = getAction("update-debounced");
const input = ref({ prompt: props.agent.prompt });

const agentForm: Form = {
	fields: [
		{
			name: "prompt",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				editorClass: "min-h-[80vh] bg-slate-600 rounded p-4",
				maxLength: 100000
			}),
			label: "Prompt Milk"
		}
	]
};
</script>
