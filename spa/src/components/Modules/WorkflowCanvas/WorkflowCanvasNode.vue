<template>
	<div class="group workflow-canvas-node w-48 relative">
		<div v-if="loading" class="absolute-top-left w-56 h-24 left-[-1rem] flex items-center justify-center z-[2000]">
			<LoadingSandLottie class="w-32" autoplay />
		</div>
		<div
			class="node-content relative border  rounded-xl text-lg h-24"
			:class="nodeClass"
		>
			<NodeHeaderBar
				class="opacity-0 group-hover:opacity-100 transition-all absolute-top-left w-52 z-10 top-[-2.5rem] left-[-.5rem]"
				:task-run="taskRun"
				:temporary="isTemporary"
				:loading="loading"
				@copy="$emit('copy', node)"
				@edit="$emit('edit', node)"
				@remove="$emit('remove', node)"
				@restart="refreshActiveWorkflowRun"
			/>

			<div class="flex justify-center items-center h-full">
				<Component
					:is="taskRunner.node?.is || BaseTaskRunnerNode"
					v-if="workflowNode"
					v-bind="taskRunner.node || {}"
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
		<div class="mt-2 flex justify-center">
			<div>
				<div class="node-title text-center">{{ node.data.name }}</div>
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
		</div>
	</div>
</template>

<script setup lang="ts">
import LoadingSandLottie from "@/assets/dotlottie/LoadingSandLottie";
import { BaseTaskRunnerNode } from "@/components/Modules/TaskDefinitions/TaskRunners/Nodes";
import NodeHeaderBar from "@/components/Modules/WorkflowCanvas/NodeHeaderBar";
import { useWorkflowNode } from "@/components/Modules/WorkflowCanvas/useWorkflowNode";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { refreshActiveWorkflowRun } from "@/components/Modules/WorkflowDefinitions/store";
import { TaskRun, WorkflowDefinition, WorkflowRun } from "@/types";
import { Node } from "@vue-flow/core";
import { computed } from "vue";

defineEmits<{
	(e: "copy", node: Node): void;
	(e: "edit", node: Node): void;
	(e: "remove", node: Node): void;
}>();

const props = defineProps<{
	node: Node;
	workflowDefinition: WorkflowDefinition;
	workflowRun?: WorkflowRun;
	loading?: boolean;
}>();

// Is this node a temporary placeholder waiting for the backend to respond with the real node ID
const isTemporary = computed(() => !!props.node.id.match(/^td-/));

const workflowNode = computed(() => props.workflowDefinition.nodes?.find((wn) => wn.id == +props.node.id));
const taskRun = computed<TaskRun>(() => props.workflowRun?.taskRuns?.find((tr) => tr.workflow_node_id == +props.node.id));

const {
	taskRunner,
	isTaskRunning,
	isTaskCompleted,
	isTaskFailed,
	isTaskPending
} = useWorkflowNode(workflowNode, taskRun);

const nodeClass = computed(() => {
	return {
		"opacity-50": props.loading,
		"border-gray-300 bg-slate-700": isTaskPending.value,
		"bg-sky-900 border-sky-400": isTaskRunning.value,
		"bg-red-900 border-red-400": isTaskFailed.value,
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
