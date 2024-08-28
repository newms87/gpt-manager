<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="agent"
			:form="agentForm"
		/>

		<QSeparator class="bg-slate-500 my-8" />

		<AgentDirectiveField :agent="agent" />
		
		<QSeparator class="bg-slate-500 my-8" />

		<AgentSchemaField :agent="agent" />

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
					:format="agent.response_format === 'text' ? 'text' : agent.schema_format"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Agents/agentActions";
import AgentDirectiveField from "@/components/Modules/Agents/Fields/AgentDirectiveField";
import AgentSchemaField from "@/components/Modules/Agents/Fields/AgentSchemaField";
import { ActionButton } from "@/components/Shared";
import { Agent } from "@/types/agents";
import { FaSolidRobot as GenerateSampleIcon } from "danx-icon";
import { ActionForm, BooleanField, Form, NumberField, SelectField } from "quasar-ui-danx";
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
			label: "Response Format",
			vnode: (props) => h(SelectField, {
				...props,
				options: [
					{ label: "Text", value: "text" },
					{ label: "JSON Object", value: "json_object" },
					{ label: "JSON Schema", value: "json_schema" }
				]
			})
		},
		{
			name: "enable_message_sources",
			label: "Enable Message Sources?",
			vnode: (props) => h(BooleanField, { ...props })
		},
		{
			name: "retry_count",
			label: "Valid response retries?",
			vnode: (props) => h(NumberField, { ...props, class: "w-40" })
		},
		{
			name: "response_notes",
			label: "Directive",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				maxLength: 10000
			})
		}
	]
};
</script>
