<template>
	<div class="py-2 px-4 border rounded-lg bg-slate-800">
		<div class="flex items-center flex-nowrap space-x-2">
			<LabelPillWidget :label="`TaskWorkflowRun: ${taskWorkflowRun.id}`" color="sky" size="xs" />
			<div class="flex-grow">{{ taskWorkflowRun.name }}</div>
			<WorkflowStatusTimerPill :runner="taskWorkflowRun" />
			<ShowHideButton v-model="isShowing" class="bg-sky-900" />
			<ActionButton
				v-if="selectable"
				type="confirm"
				color="green-invert"
				label="select"
				class="text-xs"
				@click="$emit('select')"
			/>
			<ActionButton type="trash" color="red" :action="deleteTaskWorkflowRunAction" :target="taskWorkflowRun" />
		</div>
		<div v-if="isShowing" class="py-4">
			<TaskRunCard v-for="taskRun in taskWorkflowRun.taskRuns" :key="taskRun.id" :task-run="taskRun" class="my-2" />
		</div>
	</div>
</template>
<script setup lang="ts">
import TaskRunCard from "@/components/Modules/TaskDefinitions/Panels/TaskRunCard";
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import { dxTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/TaskWorkflowRuns/config";
import { WORKFLOW_STATUS } from "@/components/Modules/TaskWorkflows/workflows";
import { TaskWorkflow, TaskWorkflowRun } from "@/types/task-workflows";
import {
	ActionButton,
	autoRefreshObject,
	LabelPillWidget,
	ShowHideButton,
	stopAutoRefreshObject
} from "quasar-ui-danx";
import { onMounted, onUnmounted, ref } from "vue";

defineEmits(["select"]);
const props = defineProps<{
	taskWorkflow: TaskWorkflow;
	taskWorkflowRun: TaskWorkflowRun;
	selectable?: boolean;
}>();

const deleteTaskWorkflowRunAction = dxTaskWorkflowRun.getAction("delete", { onFinish: () => dxTaskWorkflow.routes.details(props.taskWorkflow) });
const isShowing = ref(false);

/********
 * Refresh the task run every 2 seconds while it is running
 */
const autoRefreshName = "task-workflow-run:" + props.taskWorkflowRun.id;
onMounted(() => {
	autoRefreshObject(
		autoRefreshName,
		props.taskWorkflowRun,
		(tr: TaskWorkflowRun) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value].includes(tr.status),
		(tr: TaskWorkflowRun) => dxTaskWorkflowRun.routes.details(tr, { taskRuns: true })
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(autoRefreshName);
});
</script>
