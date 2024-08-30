<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="promptSchema"
			:form="promptSchemaForm"
		/>

		<QSeparator class="bg-slate-500 my-8" />

		<div>
			<h3>Example Response</h3>
			<ActionButton
				:action="generateExampleAction"
				:target="promptSchema"
				class="my-4 bg-sky-800 text-base px-6"
				:icon="GenerateExampleIcon"
				icon-class="w-5"
				label="Generate Example Response"
				:loading="generateExampleAction.isApplying"
				@click="generateExampleAction.trigger(promptSchema)"
			/>
			<div v-if="promptSchema.response_example">
				<MarkdownEditor
					readonly
					:model-value="promptSchema.response_example"
					sync-model-changes
					:format="promptSchema.schema_format"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { ActionButton } from "@/components/Shared";
import { PromptSchema } from "@/types/prompts";
import { FaSolidRobot as GenerateExampleIcon } from "danx-icon";
import { ActionForm, Form, SelectField } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	promptSchema: PromptSchema,
}>();

const updateDebouncedAction = getAction("update-debounced");
const generateExampleAction = getAction("generate-example");

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
