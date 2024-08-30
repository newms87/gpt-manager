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
				:target="agent"
				class="my-4 bg-sky-800 text-base px-6"
				:icon="GenerateSampleIcon"
				icon-class="w-5"
				label="Generate Sample"
				:loading="sampleAction.isApplying"
				@click="sampleAction.trigger(agent)"
			/>
			<div v-if="agent.response_sample">
				<MarkdownEditor
					readonly
					:model-value="agent.response_sample"
					sync-model-changes
					:format="agent.response_format === 'text' ? 'text' : agent.schema_format"
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
import { FaSolidRobot as GenerateSampleIcon } from "danx-icon";
import { ActionForm, Form, SelectField } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	promptSchema: PromptSchema,
}>();

const updateDebouncedAction = getAction("update-debounced");
const sampleAction = getAction("generate-sample");

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
