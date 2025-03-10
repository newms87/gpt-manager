<template>
	<div class="workflow-editor overflow-hidden relative">
		<VueFlow
			id="workflow-canvas-vf"
			v-model="nodes"
			v-model:edges="edges"
			:default-zoom="1"
			:min-zoom="0.2"
			:max-zoom="4"
			snap-to-grid
			:snap-grid="[20, 20]"
			class="workflow-canvas"
			elevate-edges-on-select
			@connect="onConnectionAdd"
			@node-drag-stop="onNodeDragStop"
			@dragover="onDragOver"
			@drop="e => handleExternalDrop('workflow-canvas-vf', e)"
		>
			<template #node-custom="nodeProps">
				<WorkflowCanvasNode
					:node="nodeProps"
					:task-workflow="taskWorkflow"
					:task-workflow-run="taskWorkflowRun"
					:loading="loading"
					@edit="node => $emit('node-edit', resolveWorkflowNode(node))"
					@remove="node => $emit('node-remove', resolveWorkflowNode(node))"
				/>
			</template>
			<template #edge-custom="edgeProps">
				<WorkflowCanvasEdge
					:edge="edgeProps"
					:nodes="nodes"
					:task-workflow-run="taskWorkflowRun"
					@remove="onConnectionRemove"
				/>
			</template>
			<template #connection-line="connectionLineProps">
				<WorkflowCanvasConnectionLine v-bind="connectionLineProps" />
			</template>

			<Background variant="dots" />
			<Panel position="top-right">
				<BionicManRunningLottie
					autoplay
					class="w-40 relative right-[-1.5rem] opacity-0"
					:class="{'opacity-100': isRunning}"
				/>
			</Panel>
		</VueFlow>
	</div>
</template>

<script setup lang="ts">
import BionicManRunningLottie from "@/assets/dotlottie/BionicManRunningLottie";
import { handleExternalDrop, onDragOver } from "@/components/Modules/WorkflowCanvas/dragNDrop";
import {
	connectWorkflowNodes,
	convertConnectionsToVueFlow,
	convertNodesToVueFlow
} from "@/components/Modules/WorkflowCanvas/helpers";
import WorkflowCanvasConnectionLine from "@/components/Modules/WorkflowCanvas/WorkflowCanvasConnectionLine";
import WorkflowCanvasEdge from "@/components/Modules/WorkflowCanvas/WorkflowCanvasEdge";
import { TaskWorkflow, TaskWorkflowConnection, TaskWorkflowNode, TaskWorkflowRun } from "@/types/task-workflows";
import { Background } from "@vue-flow/background";
import { Connection, Edge, EdgeProps, Node, Panel, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { computed, onMounted, ref, watch } from "vue";
import WorkflowCanvasNode from "./WorkflowCanvasNode.vue";

const emit = defineEmits<{
	(e: "node-click", node: TaskWorkflowNode): void;
	(e: "node-position", node: TaskWorkflowNode, position: { x: number, y: number }): void;
	(e: "node-edit", node: TaskWorkflowNode): void;
	(e: "node-remove", node: TaskWorkflowNode): void;
	(e: "connection-add", connection: TaskWorkflowConnection): void;
	(e: "connection-remove", connection: TaskWorkflowConnection): void;
}>();

const props = defineProps<{
	taskWorkflowRun?: TaskWorkflowRun;
	loading?: boolean;
}>();

const taskWorkflow = defineModel<TaskWorkflow>();

const isRunning = computed(() => props.taskWorkflowRun?.status === "Running");

// Reference to internal Vue Flow nodes
const nodes = ref<Node[]>([]);
const edges = ref<Edge[]>([]);

function convertToVueFlow() {
	if (taskWorkflow.value?.nodes) {
		nodes.value = convertNodesToVueFlow(taskWorkflow.value.nodes);
		edges.value = convertConnectionsToVueFlow(taskWorkflow.value.connections || []);
	}
}

// Watch for changes in the prop model and update the flow
watch(() => taskWorkflow.value, convertToVueFlow, { deep: true });
onMounted(convertToVueFlow);

/*********** Node Related Methods *********/
function resolveWorkflowNode(node: Node) {
	const workflowNode = taskWorkflow.value.nodes.find(n => n.id == +node.id);

	if (!workflowNode) {
		throw new Error("Workflow node not found: " + node.id);
	}

	return workflowNode;
}

function onNodeDragStop({ node }) {
	emit("node-position", resolveWorkflowNode(node), { ...node.position });
}

/*********** Connection Related Methods *********/
function resolveWorkflowConnection(edge: EdgeProps) {
	const workflowNode = taskWorkflow.value.connections.find(c => c.id == +edge.id);

	if (!workflowNode) {
		throw new Error("Workflow node not found: " + edge.id);
	}

	return workflowNode;
}

function onConnectionAdd(connection: Connection) {
	const connections = connectWorkflowNodes(taskWorkflow.value.connections, connection);
	if (connections) {
		emit("connection-add", connections.pop());
	}
}

function onConnectionRemove(edge: EdgeProps) {
	emit("connection-remove", resolveWorkflowConnection(edge));
}

</script>
