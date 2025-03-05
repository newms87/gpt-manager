// Convert workflow model to Vue Flow format
import { TaskWorkflowConnection, TaskWorkflowNode } from "@/types/task-workflows";
import { Connection, Position } from "@vue-flow/core";
import { nanoid } from "nanoid";

export function convertNodesToVueFlow(workflowNodes: TaskWorkflowNode[]) {
	const vueFlowNodes = [];
	for (const workflowNode of workflowNodes) {
		vueFlowNodes.push({
			id: workflowNode.id?.toString() || nanoid(),
			type: "custom",
			position: { x: workflowNode.settings?.x || 0, y: workflowNode.settings?.y || 0 },
			data: {
				name: workflowNode.taskDefinition?.name || workflowNode.name,
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

export function convertEdgesToVueFlow(workflowEdges) {
	const vueFlowEdges = [];
	for (const workflowEdge of workflowEdges) {
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

export function connectWorkflowNodes(currentConnections: TaskWorkflowConnection[], newConnection: Connection) {
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
