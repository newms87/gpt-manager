<template>
	<div class="relative h-full overflow-hidden flex flex-col flex-nowrap">
		<div class="flex flex-nowrap space-x-4 p-3">
			<SelectionMenuField
				:selected="activeWorkflowDefinition"
				selectable
				name-editable
				creatable
				deletable
				:select-icon="WorkflowIcon"
				label-class="text-slate-300"
				:options="workflowDefinitions"
				:loading="isLoadingWorkflowDefinitions"
				@update:selected="(workflowDefinition: WorkflowDefinition) => setActiveWorkflowDefinition(workflowDefinition)"
				@create="createAction.trigger"
				@update="input => updateAction.trigger(activeWorkflowDefinition, input)"
				@delete="workflowDefinition => deleteAction.trigger(workflowDefinition)"
			/>
			<div class="flex-grow" />
			<WorkflowDefinitionHeaderBar v-if="activeWorkflowDefinition" />
		</div>
		<div class="flex flex-grow items-center justify-center overflow-hidden">
			<WorkflowDefinitionEditor v-if="activeWorkflowDefinition" />
		</div>
	</div>
</template>
<script setup lang="ts">
import {
	dxWorkflowDefinition,
	WorkflowDefinitionEditor,
	WorkflowDefinitionHeaderBar
} from "@/components/Modules/WorkflowDefinitions";
import {
	activeWorkflowDefinition,
	initWorkflowState,
	isLoadingWorkflowDefinitions,
	loadWorkflowDefinitions,
	setActiveWorkflowDefinition,
	workflowDefinitions
} from "@/components/Modules/WorkflowDefinitions/store";
import { WorkflowDefinition } from "@/types";
import { FaSolidAnkh as WorkflowIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { onMounted } from "vue";

onMounted(initWorkflowState);

const createAction = dxWorkflowDefinition.getAction("quick-create", { onFinish: loadWorkflowDefinitions });
const updateAction = dxWorkflowDefinition.getAction("update");
const deleteAction = dxWorkflowDefinition.getAction("delete", { onFinish: loadWorkflowDefinitions });
</script>
