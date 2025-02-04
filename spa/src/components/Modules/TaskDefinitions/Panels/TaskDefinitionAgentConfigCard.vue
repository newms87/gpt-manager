<template>
	<div>
		<SelectionMenuField
			v-model:editing="isEditingAgent"
			:selected="taskDefinitionAgent.agent"
			selectable
			editable
			creatable
			:select-icon="AgentIcon"
			select-class="bg-emerald-900 text-cyan-400"
			label-class="text-slate-300"
			:options="dxAgent.pagedItems.value?.data || []"
			@update:selected="agent => $emit('update', { agent_id: agent.id })"
		/>

		<div class="mt-4">
			<SchemaEditorToolbox
				can-select
				can-select-fragment
				previewable
				button-color="bg-sky-900 text-sky-200"
				:model-value="taskDefinitionAgent.inputSchema"
				:fragment="taskDefinitionAgent.inputSchemaFragment"
				@update:model-value="schema => $emit('update', { input_schema_id: schema?.id || null })"
				@update:fragment="fragment => $emit('update', { input_schema_fragment_id: fragment?.id || null })"
			>
				<template #header-start>
					<div class="bg-sky-900 text-sky-200 rounded w-20 text-center py-1.5 text-sm mr-4">Input</div>
				</template>
			</SchemaEditorToolbox>
		</div>

		<div class="mt-4">
			<SchemaEditorToolbox
				can-select
				can-select-fragment
				previewable
				button-color="bg-green-900 text-green-200"
				:model-value="taskDefinitionAgent.inputSchema"
				:fragment="taskDefinitionAgent.inputSchemaFragment"
				@update:model-value="schema => $emit('update', { input_schema_id: schema?.id || null })"
				@update:fragment="fragment => $emit('update', { input_schema_fragment_id: fragment?.id || null })"
			>
				<template #header-start>
					<div class="bg-green-900 text-green-200 rounded w-20 text-center py-1.5 text-sm mr-4">Output</div>
				</template>
			</SchemaEditorToolbox>
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
