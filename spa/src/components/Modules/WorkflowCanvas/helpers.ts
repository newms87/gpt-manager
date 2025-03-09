import { TaskWorkflowConnection, TaskWorkflowNode } from "@/types/task-workflows";
import { Connection, Position, useVueFlow } from "@vue-flow/core";
import { nanoid } from "nanoid";

const { edges } = useVueFlow();

/**
 *  Convert TaskWorkflowNodes to VueFlow nodes
 */
function convertNodesToVueFlow(workflowNodes: TaskWorkflowNode[]) {
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
 *  Convert TaskWorkflowConnections to VueFlow edges
 */
function convertConnectionsToVueFlow(workflowConnections: TaskWorkflowConnection[]) {
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

function connectWorkflowNodes(currentConnections: TaskWorkflowConnection[], newConnection: Connection) {
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
	} as TaskWorkflowConnection);
	return newConnections;
}

export {
	edges,
	convertNodesToVueFlow,
	convertConnectionsToVueFlow,
	connectWorkflowNodes
};
