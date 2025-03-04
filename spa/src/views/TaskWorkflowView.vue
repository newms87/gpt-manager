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
				:loading="isLoading"
				@update:selected="taskWorkflow => onSelect(taskWorkflow.id as string)"
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
import TaskWorkflowRunsDrawer from "@/components/Modules/TaskWorkflows/TaskWorkflowRunsDrawer";
import { FaSolidAnkh as WorkflowIcon } from "danx-icon";
import { getItem, SelectionMenuField, setItem, storeObjects } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const ACTIVE_TASK_WORKFLOW_KEY = "dx-active-task-workflow-id";

onMounted(loadTaskWorkflows);

const createAction = dxTaskWorkflow.getAction("quick-create", { onFinish: loadTaskWorkflows });
const updateAction = dxTaskWorkflow.getAction("update");
const deleteAction = dxTaskWorkflow.getAction("delete", { onFinish: loadTaskWorkflows });

const isLoading = ref(false);
const taskWorkflows = ref([]);
const activeTaskWorkflowId = ref<string | null>(null);
const activeTaskWorkflow = computed(() => taskWorkflows.value.find(tw => tw.id === activeTaskWorkflowId.value));

async function loadTaskWorkflows() {
	isLoading.value = true;
	taskWorkflows.value = storeObjects((await dxTaskWorkflow.routes.list()).data);
	await onSelect(getItem(ACTIVE_TASK_WORKFLOW_KEY));
	isLoading.value = false;
}

async function onSelect(taskWorkflowId: string) {
	setItem(ACTIVE_TASK_WORKFLOW_KEY, taskWorkflowId);
	activeTaskWorkflowId.value = taskWorkflowId;

	if (activeTaskWorkflow.value) {
		await dxTaskWorkflow.routes.detailsAndStore(activeTaskWorkflow.value);
	}
}
</script>
