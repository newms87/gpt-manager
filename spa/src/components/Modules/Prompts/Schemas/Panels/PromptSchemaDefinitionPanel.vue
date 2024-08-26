<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="promptSchema"
			:form="promptSchemaForm"
		/>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { PromptSchema } from "@/types/prompts";
import { ActionForm, Form } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	promptSchema: PromptSchema,
}>();

const updateDebouncedAction = getAction("update-debounced");

const promptSchemaForm: Form = {
	fields: [
		{
			name: "schema",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				maxLength: 64000
			}),
			label: "Schema"
		}
	]
};
</script>
