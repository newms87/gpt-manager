<template>
	<div class="relative h-full overflow-hidden flex flex-col flex-nowrap">
		<div class="flex flex-nowrap space-x-4 p-3">
			<SelectionMenuField
				:selected="activeTaskWorkflow"
				selectable
				name-editable
				creatable
				deletable
				:select-icon="WorkflowIcon"
				label-class="text-slate-300"
				:options="taskWorkflows"
				:loading="isLoadingWorkflows"
				@update:selected="(taskWorkflow: TaskWorkflow) => setActiveTaskWorkflow(taskWorkflow)"
				@create="createAction.trigger"
				@update="input => updateAction.trigger(activeTaskWorkflow, input)"
				@delete="taskWorkflow => deleteAction.trigger(taskWorkflow)"
			/>
			<div class="flex-grow" />
			<TaskWorkflowRunsDrawer v-if="activeTaskWorkflow" :task-workflow="activeTaskWorkflow" />
		</div>
		<div class="flex flex-grow items-center justify-center overflow-hidden">
			<TaskWorkflowEditor v-if="activeTaskWorkflow" :task-workflow="activeTaskWorkflow" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflow, TaskWorkflowEditor } from "@/components/Modules/TaskWorkflows";
import {
	activeTaskWorkflow,
	initWorkflowState,
	isLoadingWorkflows,
	loadTaskWorkflows,
	setActiveTaskWorkflow,
	taskWorkflows
} from "@/components/Modules/TaskWorkflows/store";
import TaskWorkflowRunsDrawer from "@/components/Modules/TaskWorkflows/TaskWorkflowRunsDrawer";
import { TaskWorkflow } from "@/types/task-workflows";
import { FaSolidAnkh as WorkflowIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { onMounted } from "vue";

onMounted(initWorkflowState);

const createAction = dxTaskWorkflow.getAction("quick-create", { onFinish: loadTaskWorkflows });
const updateAction = dxTaskWorkflow.getAction("update");
const deleteAction = dxTaskWorkflow.getAction("delete", { onFinish: loadTaskWorkflows });
</script>
