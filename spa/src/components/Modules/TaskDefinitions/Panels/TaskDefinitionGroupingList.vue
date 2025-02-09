<template>
	<div class="bg-slate-800 p-4 rounded">
		<div class="flex">
			<div class="pr-4 py-4">Grouping Mode:</div>
			<QTabs
				class="tab-buttons border-sky-900"
				indicator-color="sky-900"
				:model-value="taskDefinition.grouping_mode"
				@update:model-value="grouping_mode => updateAction.trigger(taskDefinition, {grouping_mode})"
			>
				<QTab name="Concatenate" label="Concatenate" />
				<QTab name="Merge" label="Merge" />
				<QTab name="Overwrite" label="Overwrite" />
				<QTab name="Split" label="Split" />
			</QTabs>
			<QCheckbox
				class="ml-8"
				:model-value="taskDefinition.split_by_file"
				label="Split by file"
				@update:model-value="split_by_file => updateAction.trigger(taskDefinition, {split_by_file})"
			/>
			<QCheckbox
				class="ml-8"
				:model-value="!!taskDefinition.groupingFragments"
				label="Group by keys"
				@update:model-value="addGroupingKey"
			/>
		</div>
		<div v-if="taskDefinition.grouping_keys" class="mt-4">
			<div v-for="(groupingKey, index) in taskDefinition.grouping_keys" :key="index" class="flex items-center">
				<SchemaEditorToolbox
					can-select
					can-select-fragment
					previewable
					:loading="updateAction.isApplying"
					:model-value="resolvePromptSchema(groupingKey.prompt_schema_id)"
					:fragment="groupingKey"
					@update:model-value="promptSchema => updateAction.trigger(taskDefinition, {grouping_keys: taskDefinition.grouping_keys})"
				/>
			</div>
			<ActionButton
				v-if="unusedPromptSchemas.length > 0"
				type="create"
				color="green"
				label="Add Grouping Key"
				@click="addGroupingKey"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxPromptSchema } from "@/components/Modules/Schemas/Schemas";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { ActionButton } from "@/components/Shared";
import { TaskDefinition } from "@/types";
import { computed } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition,
}>();

const updateAction = dxTaskDefinition.getAction("update");


// TODO: Maybe best to create a generalized prompt_schema_fragmentables table
//       prompt_schema_id
//       prompt_schema_fragment_id
//       category (ie: agent input, agent output, grouping_key, etc)
//       Replaces the task_definition_agent.output_schema_fragment_id, task_definition_agent.input_schema_fragment_id, etc


// Unused prompt schemas are the remaining prompt schemas that are not already added to the list of group keys
const unusedPromptSchemas = computed(() => {
	const unused = [];
	for (let schema of dxPromptSchema.pagedItems.value?.data || []) {
		console.log("check", schema);
		if (!props.taskDefinition.groupingFragments.find(gf => gf.schema.id === schema.id)) {
			unused.push(schema);
		}
	}
	return unused;
});

/**
 *  Resolve the prompt schema by its id
 */
function resolvePromptSchema(promptSchemaId) {
	return (dxPromptSchema.pagedItems.value?.data || []).find(schema => schema.id === promptSchemaId);
}

/**
 *  Add a new grouping key to the task definition w/ the first available prompt schema
 */
function addGroupingKey() {
	if (unusedPromptSchemas.value.length === 0) {
		return;
	}

	// TODO: consider allowing one-off selections of fragments (ie: schema_associations.fragment_selector field optional instead of a fragment_id)
	const groupingKeys = props.taskDefinition.groupingFragments || [];
	groupingKeys.push({ prompt_schema_id: unusedPromptSchemas.value[0].id });
	updateAction.trigger(props.taskDefinition, { grouping_keys: groupingKeys });
}
</script>
