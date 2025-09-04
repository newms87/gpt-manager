import { TaskRunnerClasses } from "@/components/Modules/TaskDefinitions/TaskRunners";
import { TaskRun, WorkflowNode } from "@/types";
import { Edge, useVueFlow } from "@vue-flow/core";
import { computed, Ref } from "vue";

export function useWorkflowNode(workflowNode: Ref<WorkflowNode>, taskRun: Ref<TaskRun>, instanceId: string = "workflow-canvas-vf") {
    const { edges } = useVueFlow(instanceId);

    const taskDefinition = computed(() => workflowNode.value.taskDefinition);
    const taskRunner = computed(() => TaskRunnerClasses[taskDefinition.value?.task_runner_name] || TaskRunnerClasses["AI Agent"]);
    const sourceEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.source === workflowNode.value.id.toString()));
    const targetEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.target === workflowNode.value.id.toString()));
    const isTaskRunning = computed(() => taskRun.value?.status === "Running");
    const isTaskFailed = computed(() => taskRun.value?.status === "Failed");
    const isTaskSkipped = computed(() => taskRun.value?.status === "Skipped");
    const isTaskCompleted = computed(() => taskRun.value?.status === "Completed");
    const isTaskPending = computed(() => !isTaskRunning.value && !isTaskCompleted.value && !isTaskFailed.value);

    return {
        taskDefinition,
        taskRunner,
        sourceEdges,
        targetEdges,
        isTaskRunning,
        isTaskFailed,
        isTaskSkipped,
        isTaskCompleted,
        isTaskPending
    };
}
