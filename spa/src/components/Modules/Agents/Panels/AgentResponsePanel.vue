<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="agent"
			:form="agentForm"
		/>

		<QSeparator class="bg-slate-500 my-8" />

		<h6 class="mb-4">Response Schema</h6>

		<div class="flex items-stretch flex-nowrap">
			<SelectField
				class="flex-grow"
				:model-value="agent.responseSchema?.id"
				:options="AgentController.getFieldOptions('promptSchemas')"
				@update="onChangeSchema"
			/>
			<QBtn class="bg-green-900 ml-4 w-1/5" :loading="createSchemaAction.isApplying" @click="onCreateSchema">
				Create
			</QBtn>
		</div>

		<div v-if="agent.responseSchema">
			<PromptSchemaDefinitionPanel :prompt-schema="agent.responseSchema" />
		</div>

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
import { AgentController } from "@/components/Modules/Agents/agentControls";
import { PromptSchemaDefinitionPanel } from "@/components/Modules/Prompts/Schemas/Panels";
import { getAction as getSchemaAction } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { ActionButton } from "@/components/Shared";
import { Agent } from "@/types/agents";
import { FaSolidRobot as GenerateSampleIcon } from "danx-icon";
import { ActionForm, BooleanField, Form, NumberField, SelectField } from "quasar-ui-danx";
import { h } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const updateDebouncedAction = getAction("update-debounced");
const updateAction = getAction("update");
const sampleAction = getAction("generate-sample");
const createSchemaAction = getSchemaAction("create", { onFinish: AgentController.loadFieldOptions });

async function onCreateSchema() {
	const { item: promptSchema } = await createSchemaAction.trigger();

	if (promptSchema) {
		await updateAction.trigger(props.agent, { response_schema_id: promptSchema.id });
	}
}

async function onChangeSchema(response_schema_id) {
	await updateAction.trigger(props.agent, { response_schema_id });
}

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
