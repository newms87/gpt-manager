<template>
	<div class="bg-sky-950 p-4 rounded">
		<div class="flex">
			<div class="pr-4 py-4">Grouping Mode:</div>
			<QTabs
				class="tab-buttons border-sky-900"
				indicator-color="sky-900"
				:model-value="taskDefinition.grouping_mode"
				@update:model-value="grouping_mode => updateTaskDefinitionAction.trigger(taskDefinition, {grouping_mode})"
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
				@update:model-value="split_by_file => updateTaskDefinitionAction.trigger(taskDefinition, {split_by_file})"
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
					:action="deleteAssociationAction"
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
					@update:model-value="schema => updateAssociationAction.trigger(schemaAssociation, {schema_definition_id: schema.id})"
					@update:fragment="fragment => updateAssociationAction.trigger(schemaAssociation, {schema_fragment_id: fragment.id || null})"
				/>
			</div>
			<ActionButton
				:disabled="!nextSchemaDefinition"
				type="create"
				color="green"
				size="sm"
				:label="nextSchemaDefinition ? 'Add Grouping Key' : 'All schemas added'"
				class="mt-4"
				:action="createAssociationAction"
				:input="{task_definition_id: taskDefinition.id, schema_definition_id: nextSchemaDefinition?.id, category: 'grouping'}"
				:loading="!dxSchemaDefinition.pagedItems.value || createAssociationAction.isApplying"
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

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");
const createAssociationAction = dxSchemaAssociation.getAction("quick-create", { onFinish: () => dxTaskDefinition.routes.detailsAndStore(props.taskDefinition) });
const updateAssociationAction = dxSchemaAssociation.getAction("update");
const deleteAssociationAction = dxSchemaAssociation.getAction("quick-delete", { onFinish: () => dxTaskDefinition.routes.detailsAndStore(props.taskDefinition) });

const usedSchemaIds = computed(() => props.taskDefinition.groupingSchemaAssociations?.map(s => s.schema.id) || []);

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
