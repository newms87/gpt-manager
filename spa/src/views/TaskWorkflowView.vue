<template>
	<div class="relative h-full p-6 overflow-hidden flex flex-col flex-nowrap">
		<SelectionMenuField
			:selected="activeTaskWorkflow"
			selectable
			name-editable
			creatable
			:select-icon="WorkflowIcon"
			label-class="text-slate-300"
			:options="taskWorkflows"
			:loading="isLoading"
			@update:selected="taskWorkflow => onSelect(taskWorkflow.id as string)"
			@create="createAction.trigger"
			@update="input => updateAction.trigger(activeTaskWorkflow, input)"
		/>
		<div class="flex flex-grow items-center justify-center">
			<TaskWorkflowEditor v-if="activeTaskWorkflow" :task-workflow="activeTaskWorkflow" />
		</div>
		<TaskWorkflowRunsDrawer v-if="activeTaskWorkflow" :task-workflow="activeTaskWorkflow" />
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
