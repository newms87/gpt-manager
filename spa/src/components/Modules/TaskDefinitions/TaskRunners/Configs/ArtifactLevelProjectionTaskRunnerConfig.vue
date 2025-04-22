<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-4 mt-4 space-y-6">
			<header>
				<h2 class="text-xl font-medium mb-2">Artifact Level Projection Configuration</h2>
				<p class="text-sm text-slate-600">
					Configure projection between different artifact levels. You can project content from source level artifacts
					to target level artifacts while respecting hierarchical relationships.
				</p>
			</header>

			<section>
				<h3 class="font-bold mb-2">Source Levels</h3>
				<ArtifactLevelsField
					mode="input"
					:levels="sourceLevels"
					@update:levels="updateSourceLevels"
				/>
			</section>

			<section>
				<h3 class="font-bold mb-2">Target Levels</h3>
				<ArtifactLevelsField
					mode="input"
					:levels="targetLevels"
					@update:levels="updateTargetLevels"
				/>
			</section>

			<section class="space-y-4">
				<TextField
					:model-value="textSeparator"
					label="Text Separator"
					placeholder="\n---\n"
					@update:model-value="updateTextSeparator"
				/>

				<TextField
					:model-value="textPrefix"
					label="Text Prefix"
					placeholder="From source: "
					@update:model-value="updateTextPrefix"
				/>
			</section>

			<TaskArtifactFiltersField
				:target-task-definition="taskDefinition"
				:source-task-definitions="allSourceTaskDefinitions"
			/>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { activeWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/store";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";

import type { TaskDefinition, WorkflowNode } from "@/types";
import { TextField } from "quasar-ui-danx";
import { computed } from "vue";

import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig.vue";
import ArtifactLevelsField from "./Fields/ArtifactLevelsField.vue";
import TaskArtifactFiltersField from "./Fields/TaskArtifactFiltersField.vue";

interface ProjectionConfig {
	source_levels: number[];
	target_levels: number[];
	text_separator: string;
	text_prefix: string;
}

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

// Default config values
const defaultConfig: ProjectionConfig = {
	source_levels: [0],
	target_levels: [1],
	text_separator: "\n---\n",
	text_prefix: "From source: "
};

// Get the update action for task definition
const updateAction = dxTaskDefinition.getAction("update");

// Computed values reflecting current config
const sourceLevels = computed(() => {
	return props.taskDefinition.task_runner_config?.source_levels || defaultConfig.source_levels;
});

const targetLevels = computed(() => {
	return props.taskDefinition.task_runner_config?.target_levels || defaultConfig.target_levels;
});

const textSeparator = computed(() => {
	return props.taskDefinition.task_runner_config?.text_separator || defaultConfig.text_separator;
});

const textPrefix = computed(() => {
	return props.taskDefinition.task_runner_config?.text_prefix || defaultConfig.text_prefix;
});

// Update methods that call the backend directly
function updateSourceLevels(levels: number[]) {
	const task_runner_config = {
		...props.taskDefinition.task_runner_config || {},
		source_levels: levels
	};
	updateAction.trigger(props.taskDefinition, { task_runner_config });
}

function updateTargetLevels(levels: number[]) {
	const task_runner_config = {
		...props.taskDefinition.task_runner_config || {},
		target_levels: levels
	};
	updateAction.trigger(props.taskDefinition, { task_runner_config });
}

function updateTextSeparator(value: string) {
	const task_runner_config = {
		...props.taskDefinition.task_runner_config || {},
		text_separator: value
	};
	updateAction.trigger(props.taskDefinition, { task_runner_config });
}

function updateTextPrefix(value: string) {
	const task_runner_config = {
		...props.taskDefinition.task_runner_config || {},
		text_prefix: value
	};
	updateAction.trigger(props.taskDefinition, { task_runner_config });
}

// Find all ancestor task definitions
const allSourceTaskDefinitions = computed(() => {
	if (!activeWorkflowDefinition.value || !props.taskDefinition.id) {
		return [];
	}

	// Look for the current task's node in the workflow
	const workflowNodes = activeWorkflowDefinition.value.nodes || [];
	const currentNode = workflowNodes.find(node =>
		String(node.task_definition_id) === String(props.taskDefinition.id)
	);

	if (!currentNode) {
		return [];
	}

	// Find all ancestor task definitions
	const visitedNodeIds = new Set<string | number>();
	const sourceTaskIds = new Set<string | number>();
	const sourceTaskDefinitions: TaskDefinition[] = [];

	// Start with the current node
	gatherAncestorTasks(currentNode, workflowNodes, activeWorkflowDefinition.value.connections || [], visitedNodeIds, sourceTaskIds);

	// Collect all task definitions from the gathered IDs
	for (const nodeId of Array.from(sourceTaskIds)) {
		const node = workflowNodes.find(n => String(n.id) === String(nodeId));
		if (node?.taskDefinition && !sourceTaskDefinitions.some(td => td.id === node.taskDefinition.id)) {
			sourceTaskDefinitions.push(node.taskDefinition);
		}
	}

	return sourceTaskDefinitions;
});

/**
 * Recursively gather all ancestor nodes in the workflow
 */
function gatherAncestorTasks(
	node: WorkflowNode,
	allNodes: WorkflowNode[],
	allConnections: any[],
	visitedNodeIds: Set<string | number>,
	sourceTaskIds: Set<string | number>
): void {
	// Mark this node as visited
	visitedNodeIds.add(node.id);

	// Find all incoming connections to this node
	const incomingConnections = allConnections.filter(conn =>
		(conn.target_node_id === node.id || (conn.targetNode && conn.targetNode.id === node.id))
	);

	// For each incoming connection, process its source
	for (const connection of incomingConnections) {
		const sourceNodeId = connection.source_node_id || (connection.sourceNode && connection.sourceNode.id);

		// Skip if we can't determine the source node
		if (!sourceNodeId) continue;

		// Add to sources
		sourceTaskIds.add(sourceNodeId);

		// If we haven't visited this node yet, traverse its ancestors too
		if (!visitedNodeIds.has(sourceNodeId)) {
			const sourceNode = allNodes.find(n => String(n.id) === String(sourceNodeId));
			if (sourceNode) {
				gatherAncestorTasks(sourceNode, allNodes, allConnections, visitedNodeIds, sourceTaskIds);
			}
		}
	}
}
</script>
