import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { WorkflowConnection, WorkflowNode } from "@/types";
import { Connection, Position, useVueFlow } from "@vue-flow/core";
import { nanoid } from "nanoid";
import { shallowRef } from "vue";

const { edges } = useVueFlow();

// The list of all tasks in the teams account
const taskDefinitions = shallowRef([]);

async function loadTaskDefinitions() {
	taskDefinitions.value = (await dxTaskDefinition.routes.list()).data;
}

/**
 *  Convert WorkflowNodes to VueFlow nodes
 */
function convertNodesToVueFlow(workflowNodes: WorkflowNode[]) {
	const vueFlowNodes = [];
	for (const workflowNode of workflowNodes) {
		vueFlowNodes.push({
			id: workflowNode.id?.toString() || nanoid(),
			type: "custom",
			position: { x: workflowNode.settings?.x || 0, y: workflowNode.settings?.y || 0 },
			data: {
				name: workflowNode.taskDefinition?.name || workflowNode.name,
				task_definition_id: workflowNode.taskDefinition?.id,
				inputs: ["default"],
				outputs: ["default"]
			},
			// Define ports for each input
			targetPosition: Position.Left,
			sourcePosition: Position.Right
		});
	}
	return vueFlowNodes;
}

/**
 *  Convert WorkflowConnections to VueFlow edges
 */
function convertConnectionsToVueFlow(workflowConnections: WorkflowConnection[]) {
	const vueFlowEdges = [];
	for (const workflowEdge of workflowConnections) {
		vueFlowEdges.push({
			id: workflowEdge.id.toString(),
			source: workflowEdge.source_node_id.toString(),
			target: workflowEdge.target_node_id.toString(),
			sourceHandle: "source-" + workflowEdge.source_output_port,
			targetHandle: "target-" + workflowEdge.target_input_port,
			type: "custom",
			animated: true
		});
	}
	return vueFlowEdges;
}

function connectWorkflowNodes(currentConnections: WorkflowConnection[], newConnection: Connection) {
	if (!newConnection.sourceHandle.startsWith("source-")) {
		return;
	}

	if (!newConnection.targetHandle.startsWith("target-")) {
		return;
	}

	const sourceId = +newConnection.source;
	const targetId = +newConnection.target;
	const sourcePort = newConnection.sourceHandle.replace(/^source-/, "");
	const targetPort = newConnection.targetHandle.replace(/^target-/, "");

	// Do not allow looping to self
	if (sourceId === targetId) return;

	const connectionAlreadyExists = currentConnections.find(c => c.source_node_id == sourceId && c.source_output_port == sourcePort && c.target_node_id == targetId && c.target_input_port == targetPort);
	if (connectionAlreadyExists) {
		return;
	}

	const newConnections = [...currentConnections];
	newConnections.push({
		id: nanoid(),
		source_node_id: sourceId,
		target_node_id: targetId,
		source_output_port: sourcePort,
		target_input_port: targetPort
	} as WorkflowConnection);
	return newConnections;
}

export {
	edges,
	taskDefinitions,
	loadTaskDefinitions,
	convertNodesToVueFlow,
	convertConnectionsToVueFlow,
	connectWorkflowNodes
};
