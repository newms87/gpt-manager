import { TaskRunners } from "@/components/Modules/TaskDefinitions/TaskRunners";
import { edges } from "@/components/Modules/WorkflowCanvas/helpers";
import { TaskRun, WorkflowNode } from "@/types";
import { Edge } from "@vue-flow/core";
import { computed, Ref } from "vue";

export function useWorkflowNode(workflowNode: Ref<WorkflowNode>, taskRun: Ref<TaskRun>) {
	const taskDefinition = computed(() => workflowNode.value.taskDefinition);
	const taskRunner = computed(() => TaskRunners[taskDefinition.value?.task_runner_class] || TaskRunners.Base);
	const sourceEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.source === workflowNode.value.id.toString()));
	const targetEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.target === workflowNode.value.id.toString()));
	const isTaskRunning = computed(() => taskRun.value?.status === "Running");
	const isTaskFailed = computed(() => taskRun.value?.status === "Failed");
	const isTaskCompleted = computed(() => taskRun.value?.status === "Completed");
	const isTaskPending = computed(() => !isTaskRunning.value && !isTaskCompleted.value && !isTaskFailed.value);

	return {
		taskDefinition,
		taskRunner,
		sourceEdges,
		targetEdges,
		isTaskRunning,
		isTaskFailed,
		isTaskCompleted,
		isTaskPending
	};
}
