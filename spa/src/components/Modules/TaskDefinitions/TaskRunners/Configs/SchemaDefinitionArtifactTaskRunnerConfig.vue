<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-4">
			<div class="text-xl font-medium mb-2">Schema Definition Artifact Configuration</div>
			<div class="text-sm text-slate-600 mb-4">
				This task runner creates artifacts based on a schema definition. Select the schema
				definition that will be used to generate output artifacts.
			</div>

			<div class="flex-x text-lg font-bold mt-8">
				<SelectionMenuField
					:selected="selectedSchemaDefinition"
					selectable
					:select-icon="SchemaIcon"
					label-class="text-slate-300"
					select-text="Schema Definition"
					:options="schemaDefinitions"
					:loading="isLoadingSchemaDefinitions"
					@update:selected="onSelect"
				/>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import {
	isLoadingSchemaDefinitions,
	loadSchemaDefinitions,
	schemaDefinitions
} from "@/components/Modules/Schemas/SchemaDefinitions/store";
import { SchemaDefinition, TaskDefinition } from "@/types";
import { FaSolidDatabase as SchemaIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { computed, onMounted } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

onMounted(() => {
	loadSchemaDefinitions();
});

const selectedSchemaDefinition = computed(() =>
	schemaDefinitions.value.find(s => s.id === props.taskDefinition.task_runner_config?.schema_definition_id)
);

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

async function onSelect(schemaDefinition: SchemaDefinition) {
	await updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			schema_definition_id: schemaDefinition?.id || null
		}
	});
}
</script>
