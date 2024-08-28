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
import { ActionForm, Form, SelectField } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	promptSchema: PromptSchema,
}>();

const updateDebouncedAction = getAction("update-debounced");

const promptSchemaForm: Form = {
	fields: [
		{
			name: "schema_format",
			label: "Schema Format",
			vnode: (props) => h(SelectField, {
				...props,
				options: [
					{ label: "JSON", value: "json" },
					{ label: "YAML", value: "yaml" },
					{ label: "Typescript", value: "ts" }
				]
			})
		},
		{
			name: "schema",
			vnode: (props, input) => h(MarkdownEditor, {
				...props,
				format: input.schema_format,
				maxLength: 64000
			}),
			label: "Schema"
		}
	]
};
</script>
