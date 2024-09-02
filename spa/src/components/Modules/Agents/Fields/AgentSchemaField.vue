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

		<div v-if="agent.response_format !== 'text'" class="mt-4">
			<SelectOrCreateField
				v-model:editing="isEditingSchema"
				:selected="agent.responseSchema?.id"
				:show-edit="!!agent.responseSchema"
				:options="dxAgent.getFieldOptions('promptSchemas')"
				:loading="createSchemaAction.isApplying"
				@create="onCreateSchema"
				@update:selected="onChangeSchema"
			/>
		</div>

		<div v-if="isEditingSchema && agent.responseSchema">
			<ActionForm
				class="px-6 pt-6"
				:action="updateDebouncedSchemaAction"
				:target="agent.responseSchema"
				:form="{fields}"
				hide-saved-at
				@saved="dxAgent.loadFieldOptions"
			/>
			<PromptSchemaDefinitionPanel class="pt-0" :prompt-schema="agent.responseSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/config/actions";
import { dxAgent } from "@/components/Modules/Agents/config/controls";
import { getAction as getSchemaAction } from "@/components/Modules/Prompts/Schemas/config/actions";
import { PromptSchemaDefinitionPanel } from "@/components/Modules/Prompts/Schemas/Panels";
import { Agent } from "@/types/agents";
import { ActionForm, SelectOrCreateField, TextField } from "quasar-ui-danx";
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
const createSchemaAction = getSchemaAction("create", { onFinish: dxAgent.loadFieldOptions });
const updateDebouncedSchemaAction = getSchemaAction("update-debounced", { onFinish: dxAgent.loadFieldOptions });

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
