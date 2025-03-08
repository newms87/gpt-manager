<template>
	<div class="workflow-canvas-node w-48">
		<div class="node-content">
			<NodeHeaderBar
				:task-run="taskRun"
				:temporary="isTemporary"
				@edit="$emit('edit', node)"
				@remove="$emit('remove', node)"
			/>

			<NodeArtifactsWidget class="node-body mt-4" :task-run="taskRun">
				<ShowTaskProcessesButton v-if="taskRun" :task-run="taskRun" class="bg-sky-950 py-0" icon-class="w-3" />
			</NodeArtifactsWidget>

			<!-- Input Ports -->
			<div class="ports input-ports">
				<Handle
					id="target-default"
					type="target"
					position="left"
					class="node-handle"
					:class="{'is-connected': isTargetConnected('target-default')}"
				/>
			</div>

			<!-- Output Ports -->
			<div class="ports output-ports">
				<Handle
					id="source-default"
					type="source"
					position="right"
					class="node-handle"
					:class="{'is-connected': isSourceConnected('source-default')}"
				/>
			</div>
		</div>
		<div class="mt-2">
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
</template>

<script setup lang="ts">
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import { activeTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/store";
import NodeArtifactsWidget from "@/components/Modules/WorkflowCanvas/NodeArtifactsWidget";
import NodeHeaderBar from "@/components/Modules/WorkflowCanvas/NodeHeaderBar";
import ShowTaskProcessesButton from "@/components/Modules/WorkflowCanvas/ShowTaskProcessesButton";
import { TaskRun, TaskWorkflowRun } from "@/types";
import { Edge, Handle, Node, useVueFlow } from "@vue-flow/core";
import { computed } from "vue";

const { edges } = useVueFlow();

defineEmits<{
	(e: "edit", node: Node): void;
	(e: "remove", node: Node): void;
}>();

const props = defineProps<{
	node: Node;
	taskWorkflowRun?: TaskWorkflowRun;
}>();

// Is this node a temporary placeholder waiting for the backend to respond with the real node ID
const isTemporary = computed(() => !!props.node.id.match(/^td-/));

const sourceEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.source === props.node.id.toString()));
const targetEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.target === props.node.id.toString()));
function isSourceConnected(id) {
	return sourceEdges.value.some((edge) => edge.sourceHandle === id);
}
function isTargetConnected(id) {
	return targetEdges.value.some((edge) => edge.targetHandle === id);
}

const taskRun = computed<TaskRun>(() => props.taskWorkflowRun?.taskRuns?.find((taskRun) => taskRun.task_workflow_node_id == +props.node.id));
const isWorkflowRunning = computed(() => ["Running"].includes(activeTaskWorkflowRun.value?.status));
</script>

<style lang="scss" scoped>
.workflow-canvas-node {
	.node-content {
		@apply border border-gray-300 rounded-xl p-4 bg-sky-900 text-lg;
	}

	.node-handle {
		@apply w-4 h-4 bg-slate-400;

		&:hover {
			@apply bg-sky-800;
		}

		&.is-connected {
			@apply bg-green-500;
		}
	}
}


</style>
