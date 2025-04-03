<template>
	<div class="schema-and-fragments-config">
		<SchemaEditorToolbox
			can-select
			:can-select-fragment="maxFragments === 1"
			previewable
			editable
			hide-save-state
			button-color="bg-sky-900 text-sky-200"
			:model-value="taskDefinition.schemaDefinition"
			:fragment="(maxFragments === 1 && taskDefinition.schemaAssociations?.length > 0) ? taskDefinition.schemaAssociations[0].fragment : null"
			:loading="taskDefinition.isSaving"
			@update:model-value="onChangeSchema"
			@update:fragment="fragment => onUpdateFragment(fragment)"
		/>

		<ListTransition v-if="maxFragments > 1" class="space-y-4 mt-4">
			<div
				v-for="schemaAssociation in taskDefinition.schemaAssociations"
				:key="schemaAssociation.id"
				class="flex items-start flex-nowrap"
			>
				<ActionButton
					type="minus"
					color="orange"
					:action="deleteSchemaAssociationAction"
					:target="schemaAssociation"
					class="mr-4"
					tooltip="Remove Fragment"
				/>

				<SchemaEditorToolbox
					can-select-fragment
					previewable
					editable
					hide-save-state
					button-color="bg-sky-900 text-sky-200"
					:model-value="taskDefinition.schemaDefinition"
					:fragment="schemaAssociation.fragment"
					:loading="schemaAssociation.isSaving"
					@update:fragment="fragment => onUpdateFragment(fragment, schemaAssociation)"
				/>
			</div>

			<ActionButton
				v-if="!isFragmentLimitReached"
				type="create"
				color="sky"
				label="Add Fragment"
				size="sm"
				class="px-4 mt-6"
				:action="addSchemaAssociationAction"
				:target="taskDefinition"
			/>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxSchemaAssociation } from "@/components/Modules/Schemas/SchemaAssociations";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { SchemaAssociation, SchemaDefinition, SchemaFragment, TaskDefinition } from "@/types";
import { ActionButton, ListTransition } from "quasar-ui-danx";
import { computed } from "vue";

const props = withDefaults(defineProps<{
	taskDefinition: TaskDefinition;
	maxFragments?: number;
}>(), {
	maxFragments: 20
});

const fragmentCount = computed(() => props.taskDefinition.schemaAssociations?.length || 0);
const isFragmentLimitReached = computed(() => fragmentCount.value >= props.maxFragments);
const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");
const addSchemaAssociationAction = dxTaskDefinition.getAction("add-fragment");
const updateSchemaAssociationAction = dxSchemaAssociation.getAction("update");
const deleteSchemaAssociationAction = dxSchemaAssociation.getAction("quick-delete", { onFinish: async () => dxTaskDefinition.routes.details(props.taskDefinition) });

async function onChangeSchema(schemaDefinition: SchemaDefinition) {
	await updateTaskDefinitionAction.trigger(props.taskDefinition, { schema_definition_id: schemaDefinition?.id });
}

async function onUpdateFragment(fragment: SchemaFragment, schemaAssociation?: SchemaAssociation) {
	if (!schemaAssociation) {
		if (!props.taskDefinition.schemaAssociations?.length) {
			return await addSchemaAssociationAction.trigger(props.taskDefinition, { schema_fragment_id: fragment?.id || null });
		} else {
			schemaAssociation = props.taskDefinition.schemaAssociations[0];
		}
	}
	return await updateSchemaAssociationAction.trigger(schemaAssociation, { schema_fragment_id: fragment?.id || null });
}
</script>
