<template>
	<div>
		<div class="flex items-center flex-nowrap">
			<h6>Response Schema</h6>
			<QTabs
				class="ml-4 tab-buttons border-sky-900"
				indicator-color="sky-900"
				:model-value="agent.response_format"
				@update:model-value="response_format => updateAction.trigger(agent, {response_format})"
			>
				<QTab name="text" label="Text" />
				<QTab name="json_object" label="JSON Object" />
				<QTab name="json_schema" label="JSON Schema" />
			</QTabs>
			<template v-if="agent.response_format !== 'text'">
				<QCheckbox
					:model-value="!!agent.save_response_to_db"
					class="ml-4"
					label="Save response to DB"
					@update:model-value="save_response_to_db => updateAction.trigger(agent, {save_response_to_db})"
				/>
				<QCheckbox
					:model-value="!!agent.enable_message_sources"
					class="ml-4"
					label="Enable Message Sources"
					@update:model-value="enable_message_sources => updateAction.trigger(agent, {enable_message_sources})"
				/>
			</template>
		</div>

		<div v-if="agent.response_format !== 'text'" class="mt-4">
			<SchemaEditorToolbox
				v-model:isEditingSchema="isEditingSchema"
				can-select
				can-select-fragment
				show-preview
				:sub-selection="agent.response_sub_selection"
				:model-value="agent.responseSchema"
				@update:model-value="promptSchema => updateAction.trigger(props.agent, { response_schema_id: promptSchema.id })"
				@update:sub-selection="response_sub_selection => updateAction.trigger(props.agent, { response_sub_selection })"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { Agent } from "@/types/agents";
import { ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const isEditingSchema = ref(false);
const updateAction = dxAgent.getAction("update");
</script>
