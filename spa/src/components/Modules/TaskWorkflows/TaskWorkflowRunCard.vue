<template>
	<div class="py-2 px-4 border rounded-lg bg-slate-800">
		<div class="flex items-center flex-nowrap space-x-2">
			<LabelPillWidget :label="`TaskWorkflowRun: ${taskWorkflowRun.id}`" color="sky" size="xs" />
			<div class="flex-grow">{{ taskWorkflowRun.name }}</div>
			<ShowHideButton v-model="isShowing" class="bg-sky-900" />
			<ActionButton type="trash" color="red" :action="deleteTaskWorkflowRunAction" :target="taskWorkflowRun" />
		</div>
		<div v-if="isShowing" class="py-4">
			<TaskRunCard v-for="taskRun in taskWorkflowRun.taskRuns" :key="taskRun.id" :task-run="taskRun" />
		</div>
	</div>
</template>
<script setup lang="ts">
import TaskRunCard from "@/components/Modules/TaskDefinitions/Panels/TaskRunCard";
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import { dxTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/TaskWorkflowRuns/config";
import { ActionButton } from "@/components/Shared";
import LabelPillWidget from "@/components/Shared/Widgets/LabelPillWidget";
import { TaskWorkflow, TaskWorkflowRun } from "@/types/task-workflows";
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{
	taskWorkflow: TaskWorkflow;
	taskWorkflowRun: TaskWorkflowRun;
}>();

const deleteTaskWorkflowRunAction = dxTaskWorkflowRun.getAction("delete", { onFinish: () => dxTaskWorkflow.routes.detailsAndStore(props.taskWorkflow) });
const isShowing = ref(false);
</script>
