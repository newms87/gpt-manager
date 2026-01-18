<template>
    <div class="group workflow-canvas-node w-48 relative">
        <div v-if="loading" class="absolute-top-left w-56 h-24 left-[-1rem] flex items-center justify-center z-[2000]">
            <LoadingSandLottie class="w-32" autoplay />
        </div>

        <!-- Restart History Badge -->
        <LabelPillWidget
            v-if="taskRun?.restart_count"
            :label="`${taskRun.restart_count} restart${taskRun.restart_count > 1 ? 's' : ''}`"
            color="orange"
            size="xs"
            class="absolute top-[-0.5rem] left-[-0.5rem] z-20 whitespace-nowrap cursor-pointer hover:opacity-80 nopan nodrag"
            @click.stop="isShowingHistory = true"
            @mousedown.stop
            @pointerdown.stop
        />

        <!-- Error Badge -->
        <ErrorBadge
            v-if="taskRun"
            :error-count="taskRun.error_count"
            :url="dxTaskRun.routes.errorsUrl(taskRun)"
            :animate="isTaskRunning || isTaskFailed"
            badge-class="absolute top-[-0.5rem] right-[-0.5rem] z-20"
        />

        <div
            class="node-content relative border rounded-xl text-lg h-24"
            :class="nodeClass"
        >
            <NodeHeaderBar
                class="opacity-0 group-hover:opacity-100 transition-all absolute-top-left w-52 z-10 top-[-2.5rem] left-[-.5rem]"
                :workflow-node="workflowNode"
                :task-run="taskRun"
                :temporary="isTemporary"
                :loading="loading"
                :readonly="readonly"
                @copy="$emit('copy', node)"
                @edit="$emit('edit', node)"
                @remove="$emit('remove', node)"
            />

            <div class="flex justify-center items-center h-full">
                <Component
                    :is="taskRunner.node?.is || BaseTaskRunnerNode"
                    v-if="workflowNode"
                    v-bind="taskRunner.node || {}"
                    :instance-id="instanceId"
                    :lottie="taskRunner.lottie"
                    :workflow-node="workflowNode"
                    :task-run="taskRun"
                    :loading="loading"
                />
                <div v-else>
                    <LoadingSandLottie class="w-32 h-24" autoplay />
                </div>
            </div>
        </div>
        <div class="mt-2 flex flex-col items-center">
            <EditableDiv
                v-if="taskDefinition"
                :model-value="taskDefinition.name"
                :readonly="readonly"
                class="node-title nodrag nopan"
                color="slate-700"
                text-color="slate-200"
                content-class="text-center"
                @update:model-value="name => updateTaskDefinitionAction.trigger(taskDefinition, { name })"
            />
            <div class="flex justify-center mt-2">
                <WorkflowStatusTimerPill
                    v-if="taskRun"
                    :runner="taskRun"
                    class="text-xs"
                    status-class="rounded-full px-4"
                    timer-class="bg-slate-800 px-4 rounded-full"
                />
            </div>
        </div>

        <TaskRunHistoryDialog
            v-if="isShowingHistory"
            :task-run="taskRun"
            :is-showing="isShowingHistory"
            @close="isShowingHistory = false"
        />
    </div>
</template>

<script setup lang="ts">
import LoadingSandLottie from "@/assets/dotlottie/LoadingSandLottie";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { BaseTaskRunnerNode } from "@/components/Modules/TaskDefinitions/TaskRunners/Nodes";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import NodeHeaderBar from "@/components/Modules/WorkflowCanvas/NodeHeaderBar";
import TaskRunHistoryDialog from "@/components/Modules/WorkflowCanvas/TaskRunHistoryDialog.vue";
import { useWorkflowNode } from "@/components/Modules/WorkflowCanvas/useWorkflowNode";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import ErrorBadge from "@/components/Shared/ErrorBadge.vue";
import { TaskRun, WorkflowDefinition, WorkflowRun } from "@/types";
import { Node } from "@vue-flow/core";
import { EditableDiv, LabelPillWidget } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits<{
    (e: "copy", node: Node): void;
    (e: "edit", node: Node): void;
    (e: "remove", node: Node): void;
}>();

const props = defineProps<{
    node: Node;
    workflowDefinition: WorkflowDefinition;
    workflowRun?: WorkflowRun;
    instanceId: string;
    loading?: boolean;
    readonly?: boolean;
}>();

// Is this node a temporary placeholder waiting for the backend to respond with the real node ID
const isTemporary = computed(() => !!props.node.id.match(/^td-/));

const workflowNode = computed(() => props.workflowDefinition.nodes?.find((wn) => wn.id == +props.node.id));
const taskDefinition = computed(() => workflowNode.value?.taskDefinition);
const taskRun = computed<TaskRun>(() => props.workflowRun?.taskRuns?.find((tr) => tr.workflow_node_id == +props.node.id));

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");
const isShowingHistory = ref(false);

const {
    taskRunner,
    isTaskRunning,
    isTaskCompleted,
    isTaskSkipped,
    isTaskFailed,
    isTaskPending
} = useWorkflowNode(workflowNode, taskRun, props.instanceId);

const nodeClass = computed(() => {
    return {
        "opacity-50": props.loading,
        "border-gray-300 bg-slate-700": isTaskPending.value,
        "bg-sky-900 border-sky-400": isTaskRunning.value,
        "bg-red-900 border-red-400": isTaskFailed.value,
        "bg-skipped border-yellow-600": isTaskSkipped.value,
        "bg-green-900 border-green-400": isTaskCompleted.value
    };
});
</script>

<style lang="scss">
.vue-flow__node.selected {
    .workflow-canvas-node {
        .node-content {
            @apply outline outline-4 outline-blue-500;
        }
    }
}
</style>
