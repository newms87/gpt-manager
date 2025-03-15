<template>
	<div class="py-2 px-4 border rounded-lg bg-slate-800">
		<div class="flex items-center flex-nowrap space-x-2">
			<LabelPillWidget :label="`WorkflowRun: ${workflowRun.id}`" color="sky" size="xs" />
			<div class="flex-grow">{{ workflowRun.name }}</div>
			<WorkflowStatusTimerPill :runner="workflowRun" />
			<ShowHideButton v-model="isShowing" class="bg-sky-900" />
			<ActionButton
				v-if="selectable"
				type="confirm"
				color="green-invert"
				label="select"
				class="text-xs"
				@click="$emit('select')"
			/>
			<ActionButton type="trash" color="red" :action="deleteWorkflowRunAction" :target="workflowRun" />
		</div>
		<div v-if="isShowing" class="py-4">
			<TaskRunCard v-for="taskRun in workflowRun.taskRuns" :key="taskRun.id" :task-run="taskRun" class="my-2" />
		</div>
	</div>
</template>
<script setup lang="ts">
import TaskRunCard from "@/components/Modules/TaskDefinitions/Panels/TaskRunCard";
import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { WORKFLOW_STATUS } from "@/components/Modules/WorkflowDefinitions/workflows";
import { WorkflowDefinition, WorkflowRun } from "@/types";
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
	workflowDefinition: WorkflowDefinition;
	workflowRun: WorkflowRun;
	selectable?: boolean;
}>();

const deleteWorkflowRunAction = dxWorkflowRun.getAction("delete", { onFinish: () => dxWorkflowDefinition.routes.details(props.workflowDefinition) });
const isShowing = ref(false);

/**
 * Refresh the workflow run every 2 seconds while it is running
 */
const autoRefreshName = "workflow-run:" + props.workflowRun.id;
onMounted(() => {
	autoRefreshObject(
		autoRefreshName,
		props.workflowRun,
		(tr: WorkflowRun) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value].includes(tr.status),
		(tr: WorkflowRun) => dxWorkflowRun.routes.details(tr, { taskRuns: true })
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(autoRefreshName);
});
</script>
