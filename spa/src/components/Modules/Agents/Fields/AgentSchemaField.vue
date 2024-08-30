<template>
	<div>
		<div class="flex items-center flex-nowrap">
			<h6>Response Schema</h6>
			<QTabs
				class="ml-4 tab-buttons border-sky-900"
				indicator-color="sky-900"
				:model-value="agent.response_format"
				@update:model-value="updateAction.trigger(agent, {response_format: $event})"
			>
				<QTab name="text" label="Text" />
				<QTab name="json_object" label="JSON Object" />
				<QTab name="json_schema" label="JSON Schema" />
			</QTabs>
		</div>

		<div v-if="agent.response_format !== 'text'" class="mt-4 flex items-stretch flex-nowrap">
			<SelectField
				class="flex-grow"
				:model-value="agent.response_schema?.id"
				:options="AgentController.getFieldOptions('promptSchemas')"
				@update="onChangeSchema"
			/>
			<ShowHideButton
				v-if="agent.response_schema"
				v-model="isEditingSchema"
				label="Edit"
				class="bg-sky-800 w-1/5 ml-4"
			/>
			<QBtn class="bg-green-900 ml-4 w-1/5" :loading="createSchemaAction.isApplying" @click="onCreateSchema">
				Create Schema
			</QBtn>
		</div>

		<div v-if="isEditingSchema && agent.response_schema">
			<ActionForm
				class="px-6 pt-6"
				:action="updateDebouncedSchemaAction"
				:target="agent.response_schema"
				:form="{fields}"
				hide-saved-at
				@saved="AgentController.loadFieldOptions"
			/>
			<PromptSchemaDefinitionPanel class="pt-0" :prompt-schema="agent.response_schema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import { PromptSchemaDefinitionPanel } from "@/components/Modules/Prompts/Schemas/Panels";
import { getAction as getSchemaAction } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { ShowHideButton } from "@/components/Shared";
import { Agent } from "@/types/agents";
import { ActionForm, SelectField, TextField } from "quasar-ui-danx";
import { h, nextTick, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const fields = [{
	name: "name",
	vnode: (props) => h(TextField, { ...props, maxLength: 255 }),
	label: "Name",
	required: true
}];

const isEditingSchema = ref(false);

const updateAction = getAction("update");
const createSchemaAction = getSchemaAction("create", { onFinish: AgentController.loadFieldOptions });
const updateDebouncedSchemaAction = getSchemaAction("update-debounced", { onFinish: AgentController.loadFieldOptions });

async function onCreateSchema() {
	const { item: promptSchema } = await createSchemaAction.trigger();

	if (promptSchema) {
		await updateAction.trigger(props.agent, { response_schema_id: promptSchema.id });
	}
}

async function onChangeSchema(response_schema_id) {
	await updateAction.trigger(props.agent, { response_schema_id });
	isEditingSchema.value = false;
	await nextTick(() => {
		isEditingSchema.value = true;
	});
}
</script>
