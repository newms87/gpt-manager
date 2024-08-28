<template>
	<div>
		<div class="mb-4">Response Schema</div>

		<div class="flex items-stretch flex-nowrap">
			<SelectField
				class="flex-grow"
				:model-value="agent.responseSchema?.id"
				:options="AgentController.getFieldOptions('promptSchemas')"
				@update="onChangeSchema"
			/>
			<ShowHideButton
				v-if="agent.responseSchema"
				v-model="isEditingSchema"
				label="Edit"
				class="bg-sky-800 w-1/5 ml-4"
			/>
			<QBtn class="bg-green-900 ml-4 w-1/5" :loading="createSchemaAction.isApplying" @click="onCreateSchema">
				Create
			</QBtn>
		</div>

		<div v-if="isEditingSchema && agent.responseSchema">
			<ActionForm
				class="px-6 pt-6"
				:action="updateDebouncedSchemaAction"
				:target="agent.responseSchema"
				:form="{fields}"
				hide-saved-at
				@saved="AgentController.loadFieldOptions"
			/>
			<PromptSchemaDefinitionPanel class="pt-0" :prompt-schema="agent.responseSchema" />
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
import { h, ref } from "vue";

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
}
</script>
