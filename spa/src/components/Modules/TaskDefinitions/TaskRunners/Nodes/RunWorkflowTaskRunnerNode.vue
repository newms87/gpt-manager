<template>
	<BaseTaskRunnerNode :workflow-node="workflowNode" :task-run="taskRun">
		<RunWorkflowLottie class="w-[10rem]" :autoplay="isTaskRunning" :finished="isTaskCompleted" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { RunWorkflowLottie } from "@/assets/dotlottie";
import { useWorkflowNode } from "@/components/Modules/WorkflowCanvas/useWorkflowNode";
import { TaskRun, WorkflowNode } from "@/types";
import { toRefs } from "vue";
import BaseTaskRunnerNode from "./BaseTaskRunnerNode";

const props = defineProps<{
	workflowNode: WorkflowNode;
	taskRun?: TaskRun;
}>();

const { workflowNode, taskRun } = toRefs(props);
const { isTaskCompleted, isTaskRunning } = useWorkflowNode(workflowNode, taskRun);
</script>

