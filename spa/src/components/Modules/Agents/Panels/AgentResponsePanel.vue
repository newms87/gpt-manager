<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="agent"
			:form="agentForm"
		/>

		<QSeparator class="bg-slate-500 my-8" />

		<div>
			<h3>Sample Response</h3>
			<ActionButton
				:action="sampleAction"
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
					:format="agent.response_format"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Agents/agentActions";
import { ActionButton } from "@/components/Shared";
import { Agent } from "@/types/agents";
import { FaSolidRobot as GenerateSampleIcon } from "danx-icon";
import { ActionForm, Form, SelectField } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	agent: Agent,
}>();

const updateDebouncedAction = getAction("update-debounced");
const sampleAction = getAction("generate-sample");

const agentForm: Form = {
	fields: [
		{
			name: "response_format",
			label: "Format",
			vnode: (props) => h(SelectField, {
				...props,
				options: [
					{ label: "Text", value: "text" },
					{ label: "JSON Schema", value: "json" },
					{ label: "YAML Schema", value: "yaml" },
					{ label: "Typescript Schema", value: "ts" }
				]
			})
		},
		{
			name: "response_notes",
			label: "Directive",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				maxLength: 10000
			})
		},
		{
			name: "response_schema",
			label: "Schema",
			enabled: (input) => input.response_format !== "text",
			vnode: (field, input) => h(MarkdownEditor, {
				...field,
				format: input.response_format,
				maxLength: 100000
			})
		}
	]
};
</script>
