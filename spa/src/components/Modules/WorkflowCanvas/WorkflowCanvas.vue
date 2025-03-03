<template>
	<div class="workflow-editor overflow-hidden relative">
		<VueFlow
			v-model="nodes"
			v-model:edges="edges"
			:default-zoom="1"
			:min-zoom="0.2"
			:max-zoom="4"
			snap-to-grid
			:snap-grid="[20, 20]"
			class="workflow-canvas"
			elevate-edges-on-select
			@connect="onConnect"
			@node-click="(e) => onNodeClick(e.node)"
			@node-drag-stop="onNodeDragStop"
		>
			<template #node-custom="nodeProps">
				<WorkflowCanvasNode
					:node="nodeProps"
					@delete-node="onDeleteNode"
				/>
			</template>
			<template #edge-custom="edgeProps">
				<WorkflowCanvasEdge :edge="edgeProps" />
			</template>
			<template #connection-line="connectionLineProps">
				<WorkflowCanvasConnectionLine v-bind="connectionLineProps" />
			</template>

			<Background variant="dots" />
			<Panel position="top-right">
				<ActionButton type="play" label="Run" :action="runWorkflowAction" :target="workflowDefinition" color="green" />
			</Panel>
		</VueFlow>
	</div>
</template>

<script setup lang="ts">
import { dxTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/TaskWorkflowRuns/config";
import {
	connectWorkflowNodes,
	convertEdgesToVueFlow,
	convertNodesToVueFlow
} from "@/components/Modules/WorkflowCanvas/helpers";
import WorkflowCanvasConnectionLine from "@/components/Modules/WorkflowCanvas/WorkflowCanvasConnectionLine";
import WorkflowCanvasEdge from "@/components/Modules/WorkflowCanvas/WorkflowCanvasEdge";
import { ActionButton } from "@/components/Shared";
import { TaskWorkflow, TaskWorkflowConnection, TaskWorkflowNode } from "@/types/task-workflows";
import { Background } from "@vue-flow/background";
import { Connection, Edge, Node, Panel, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { onMounted, ref, watch } from "vue";
import WorkflowCanvasNode from "./WorkflowCanvasNode.vue";

const emit = defineEmits<{
	(e: "node-click", node: TaskWorkflowNode): void;
	(e: "node-position", node: TaskWorkflowNode, position: { x: number, y: number }): void;
	(e: "node-remove", node: TaskWorkflowNode): void;
	(e: "connection-add", connection: TaskWorkflowConnection): void;
}>();

const workflowDefinition = defineModel<TaskWorkflow>();
const runWorkflowAction = dxTaskWorkflowRun.getAction("create");

// Reference to internal Vue Flow nodes
const nodes = ref<Node[]>([]);
const edges = ref<Edge[]>([]);

function convertToVueFlow() {
	if (workflowDefinition.value?.nodes) {
		console.log("convert", workflowDefinition.value);
		nodes.value = convertNodesToVueFlow(workflowDefinition.value.nodes);
		edges.value = convertEdgesToVueFlow(workflowDefinition.value.connections || []);
	}
}

// Watch for changes in the prop model and update the flow
watch(() => workflowDefinition.value, convertToVueFlow, { deep: true });
onMounted(convertToVueFlow);

function resolveWorkflowNode(node: Node) {
	const workflowNode = workflowDefinition.value.nodes.find(n => n.id == +node.id);

	if (!workflowNode) {
		throw new Error("Workflow node not found: " + node.id);
	}

	return workflowNode;
}

function onNodeClick(node: Node) {
	emit("node-click", resolveWorkflowNode(node));
}

function onNodeDragStop({ node }) {
	emit("node-position", resolveWorkflowNode(node), { ...node.position });
}
function onDeleteNode(node) {
	emit("node-remove", resolveWorkflowNode(node));
}

// Handle new connections
function onConnect(connection: Connection) {
	const connections = connectWorkflowNodes(workflowDefinition.value.connections, connection);
	emit("connection-add", connections.pop());
}
</script>
