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
import { ActionForm, Form, SelectField } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	agent: Agent,
}>();

const updateDebouncedAction = getAction("update-debounced");

const agentForm: Form = {
	fields: [
		{
			name: "response_format",
			label: "Format",
			vnode: (props) => h(SelectField, {
				...props,
				options: [
					{ label: "Text", value: "text" },
					{ label: "JSON", value: "json_object" }
				]
			})
		},
		{
			name: "response_notes",
			label: "Notes",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				maxLength: 10000
			})
		},
		{
			name: "response_schema",
			label: "Schema",
			enabled: (input) => input.response_format === "json_object",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				forceJson: true,
				maxLength: 100000
			})
		}
	]
};
</script>
