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
		</div>
		<div class="mt-4">
			<div
				v-for="(schemaAssociation, index) in taskDefinition.groupingSchemaAssociations"
				:key="index"
				class="flex items-start flex-nowrap my-4"
			>
				<ActionButton
					type="trash"
					color="white"
					class="mr-4"
					:action="deleteAction"
					:target="schemaAssociation"
				/>
				<SchemaEditorToolbox
					can-select
					can-select-fragment
					previewable
					:exclude-schema-ids="usedSchemaIds"
					:loading="schemaAssociation.isSaving"
					:model-value="schemaAssociation.schema"
					:fragment="schemaAssociation.fragment"
					@update:model-value="schema => updateAction.trigger(schemaAssociation, {schema_definition_id: schema.id})"
					@update:fragment="fragment => updateAction.trigger(schemaAssociation, {schema_fragment_id: fragment.id || null})"
				/>
			</div>
			<ActionButton
				v-if="nextSchemaDefinition"
				type="create"
				color="green"
				size="sm"
				label="Add Grouping Key"
				class="mt-4"
				:action="createAction"
				:input="{task_definition_id: taskDefinition.id, schema_definition_id: nextSchemaDefinition.id, category: 'grouping'}"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxSchemaAssociation } from "@/components/Modules/Schemas/SchemaAssociations";
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { ActionButton } from "@/components/Shared";
import { TaskDefinition } from "@/types";
import { computed } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition,
}>();

const createAction = dxSchemaAssociation.getAction("quick-create", { onFinish: () => dxTaskDefinition.routes.detailsAndStore(props.taskDefinition) });
const updateAction = dxSchemaAssociation.getAction("update");
const deleteAction = dxSchemaAssociation.getAction("quick-delete", { onFinish: () => dxTaskDefinition.routes.detailsAndStore(props.taskDefinition) });

const usedSchemaIds = computed(() => props.taskDefinition.groupingSchemaAssociations.map(s => s.schema.id));

// The next schema to use when adding a new group (only allow using each schema once when defining the grouping keys)
const nextSchemaDefinition = computed(() => {
	for (let schema of dxSchemaDefinition.pagedItems.value?.data || []) {
		if (!usedSchemaIds.value.includes(schema.id)) {
			return schema;
		}
	}
	return null;
});
</script>
