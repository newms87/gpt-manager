<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="promptSchema"
			:form="promptSchemaForm"
		/>

		<QSeparator class="bg-slate-500 my-8" />

		<div>
			<div class="flex items-center flex-nowrap gap-x-4">
				<h6>Example Response</h6>
				<ActionButton
					:action="generateExampleAction"
					:target="promptSchema"
					class="text-base px-6"
					:class="{'bg-yellow-800': !!promptSchema.response_example, 'bg-sky-800': !promptSchema.response_example}"
					:icon="GenerateExampleIcon"
					icon-class="w-5"
					:label="promptSchema.response_example ? 'Regenerate Example' : 'Generate Example'"
					:loading="generateExampleAction.isApplying"
					@click="generateExampleAction.trigger(promptSchema)"
				/>
			</div>
			<MarkdownEditor
				:model-value="promptSchema.response_example || ''"
				sync-model-changes
				:format="promptSchema.schema_format"
				@update:model-value="updateDebouncedAction.trigger(promptSchema, { response_example: $event })"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Prompts/Schemas/config/actions";
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
