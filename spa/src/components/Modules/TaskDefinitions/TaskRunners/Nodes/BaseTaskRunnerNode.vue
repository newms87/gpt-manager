<template>
    <div class="base-task-runner-node relative h-full w-full">
        <div class="flex items-center justify-center flex-nowrap h-full w-full">
            <slot name="icon">
                <Component :is="lottie" v-if="lottie" :autoplay="isTaskRunning" :finished="isTaskCompleted" />
                <BaseNodeIcon v-else class="w-[4.4rem]" />
            </slot>
            <slot />
        </div>

        <slot name="ports">
            <NodePortsWidget
                :task-run="taskRun"
                :source-edges="sourceEdges"
                :target-edges="targetEdges"
            />
        </slot>
    </div>
</template>
<script setup lang="ts">
import NodePortsWidget from "@/components/Modules/WorkflowCanvas/NodePortsWidget";
import { useWorkflowNode } from "@/components/Modules/WorkflowCanvas/useWorkflowNode";
import { TaskRun, WorkflowNode } from "@/types";
import { FaSolidSquareShareNodes as BaseNodeIcon } from "danx-icon";
import { toRefs } from "vue";

const props = withDefaults(defineProps<{
    workflowNode: WorkflowNode;
    taskRun?: TaskRun;
    lottie?: object;
    lottieClass?: string;
    instanceId: string;
}>(), {
    taskRun: null,
    lottie: null,
    lottieClass: "w-[12rem]"
});

const { workflowNode, taskRun } = toRefs(props);
const {
    sourceEdges,
    targetEdges,
    isTaskCompleted,
    isTaskRunning
} = useWorkflowNode(workflowNode, taskRun, props.instanceId);
</script>
