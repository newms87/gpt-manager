<template>
	<div>
		<SelectionMenuField
			v-model:editing="isEditingAgent"
			:selected="taskDefinitionAgent.agent"
			selectable
			editable
			creatable
			:select-icon="AgentIcon"
			label-class="text-slate-300"
			:options="dxAgent.pagedItems.value?.data || []"
			@update:selected="agent => $emit('update', { agent_id: agent.id })"
		/>

		<div class="mt-4">
			<div class="flex items-start">
				<div class="bg-amber-900 text-amber-200 rounded px-8 py-1.5 text-sm">Input:</div>
				<SchemaEditorToolbox
					can-select
					can-select-fragment
					previewable
					button-color="bg-amber-900 text-amber-200"
					:model-value="taskDefinitionAgent.inputSchema"
					:fragment="taskDefinitionAgent.inputSchemaFragment"
					@update:model-value="schema => $emit('update', { input_schema_id: schema?.id || null })"
					@update:fragment="fragment => $emit('update', { input_schema_fragment_id: fragment?.id || null })"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { TaskDefinitionAgent } from "@/types";
import { FaSolidRobot as AgentIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["update", "remove"]);
defineProps<{
	taskDefinitionAgent: TaskDefinitionAgent;
}>();

dxAgent.initialize();

const isEditingAgent = ref(false);
</script>
