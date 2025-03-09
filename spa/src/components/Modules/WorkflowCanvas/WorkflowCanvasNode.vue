<template>
	<div class="group workflow-canvas-node w-48">
		<div class="node-content relative border border-gray-300 rounded-xl p-2 bg-sky-900 text-lg h-24">
			<NodeHeaderBar
				class="opacity-0 group-hover:opacity-100 transition-all absolute-top-left w-full p-2"
				:task-run="taskRun"
				:temporary="isTemporary"
				:loading="loading"
				@edit="$emit('edit', node)"
				@remove="$emit('remove', node)"
			/>

			<div class="flex justify-center items-center h-16">
				<Component
					:is="taskRunnerComponent"
					:config="taskDefinition.task_runner_config"
					:autoplay="isTaskRunning"
				/>
			</div>
			<NodePortsWidget
				class="node-body mt-4"
				:task-run="taskRun"
				:source-edges="sourceEdges"
				:target-edges="targetEdges"
			/>
		</div>
		<div class="mt-2 flex justify-center">
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
import { edges } from "@/components/Modules/WorkflowCanvas/helpers";
import NodeHeaderBar from "@/components/Modules/WorkflowCanvas/NodeHeaderBar";
import NodePortsWidget from "@/components/Modules/WorkflowCanvas/NodePortsWidget";
import { TaskRunners } from "@/components/Modules/WorkflowCanvas/TaskRunners";
import { TaskRun, TaskWorkflow, TaskWorkflowRun } from "@/types";
import { Edge, Node } from "@vue-flow/core";
import { computed } from "vue";

defineEmits<{
	(e: "edit", node: Node): void;
	(e: "remove", node: Node): void;
}>();

const props = defineProps<{
	node: Node;
	taskWorkflow: TaskWorkflow;
	taskWorkflowRun?: TaskWorkflowRun;
	loading?: boolean;
}>();

// Is this node a temporary placeholder waiting for the backend to respond with the real node ID
const isTemporary = computed(() => !!props.node.id.match(/^td-/));

const taskWorkflowNode = computed(() => props.taskWorkflow?.nodes.find((taskWorkflowNode) => taskWorkflowNode.id == +props.node.id));
const taskDefinition = computed(() => taskWorkflowNode.value.taskDefinition);
const taskRunnerComponent = computed(() => TaskRunners[taskDefinition.value.task_runner_class] || TaskRunners.Base);
const sourceEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.source === props.node.id.toString()));
const targetEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.target === props.node.id.toString()));
const taskRun = computed<TaskRun>(() => props.taskWorkflowRun?.taskRuns?.find((taskRun) => taskRun.task_workflow_node_id == +props.node.id));
const isTaskRunning = computed(() => taskRun.value?.status === "Running");
</script>

