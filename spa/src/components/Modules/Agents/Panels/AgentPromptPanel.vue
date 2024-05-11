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
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Agents/agentActions";
import { Agent } from "@/components/Modules/Agents/agents";
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
				editorClass: "rounded p-4",
				maxLength: 100000
			}),
			label: "Prompt Milk"
		}
	]
};
</script>
