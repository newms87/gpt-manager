<template>
	<div class="relative h-full p-6 overflow-hidden flex flex-col flex-nowrap">
		<SelectionMenuField
			:selected="activeTaskWorkflow"
			selectable
			name-editable
			creatable
			:select-icon="WorkflowIcon"
			:options="dxTaskWorkflow.pagedItems.value?.data || []"
			:loading="dxTaskWorkflow.isLoadingList.value"
			@update:selected="onSelect"
			@create="createAction.trigger"
			@update="input => updateAction.trigger(activeTaskWorkflow, input)"
		/>
		<div class="flex flex-grow items-center justify-center">
			{{ activeTaskWorkflow?.name || "Please select task workflow" }}
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows";
import { TaskWorkflow } from "@/types/task-workflows";
import { until } from "@vueuse/core";
import { FaSolidAnkh as WorkflowIcon } from "danx-icon";
import { getItem, SelectionMenuField, setItem } from "quasar-ui-danx";
import { computed, onMounted } from "vue";

const ACTIVE_TASK_WORKFLOW_KEY = "dx-active-task-workflow-id";

onMounted(init);

const createAction = dxTaskWorkflow.getAction("quick-create");
const updateAction = dxTaskWorkflow.getAction("update");

const activeTaskWorkflow = computed(() => dxTaskWorkflow.activeItem.value);

async function init() {
	dxTaskWorkflow.initialize();
	const taskWorkflowId = getItem(ACTIVE_TASK_WORKFLOW_KEY);

	if (taskWorkflowId) {
		await until(dxTaskWorkflow.pagedItems).toMatch(pi => pi?.data.length > 0);
		dxTaskWorkflow.setActiveItem(dxTaskWorkflow.pagedItems.value.data.find(ps => ps.id === taskWorkflowId));
	}
}

async function onSelect(taskWorkflow: TaskWorkflow) {
	dxTaskWorkflow.setActiveItem(taskWorkflow);
	setItem(ACTIVE_TASK_WORKFLOW_KEY, taskWorkflow?.id);
}
</script>
