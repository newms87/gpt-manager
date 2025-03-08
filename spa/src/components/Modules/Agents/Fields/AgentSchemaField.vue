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
		</div>

		<div v-if="agent.response_format !== 'text'" class="mt-4">
			<SchemaEditorToolbox
				v-model:isEditingSchema="isEditingSchema"
				can-select
				can-select-fragment
				previewing
				:loading="updateAction.isApplying"
				:model-value="agent.responseSchema"
				:fragment="agent.responseSchemaFragment"
				@update:model-value="schemaDefinition => updateAction.trigger(props.agent, { response_schema_id: schemaDefinition?.id || null })"
				@update:fragment="fragment => updateAction.trigger(props.agent, { response_schema_fragment_id: fragment?.id || null })"
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
