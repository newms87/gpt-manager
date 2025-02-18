<template>
	<div class="flex items-stretch flex-nowrap">
		<div>
			<ListTransition>
				<TaskWorkflowNodeCard
					v-for="node in taskWorkflow.nodes"
					:key="node.id"
					:task-workflow-node="node"
					class="my-4"
					:targeting-source-node="connectSourceNode"
					@delete="refreshWorkflow"
					@connect-source="connectSourceNode = node"
					@connect-target="onTargetConnection(node)"
					@cancel="connectSourceNode = null"
				/>
			</ListTransition>
			<div class="mt-8">
				<ActionButton type="create" color="green" label="Add Node" :action="addNodeAction" :target="taskWorkflow" />
			</div>
		</div>
		<div class="ml-8">
			<ListTransition>
				<TaskWorkflowConnectionLine
					v-for="connection in taskWorkflow.connections"
					:key="connection.id"
					class="my-4"
					:task-workflow-connection="connection"
					:source-node="resolveNode(connection.source_node_id)"
					:target-node="resolveNode(connection.target_node_id)"
					@delete="refreshWorkflow"
				/>
			</ListTransition>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import TaskWorkflowConnectionLine from "@/components/Modules/TaskWorkflows/TaskWorkflowConnectionLine";
import TaskWorkflowNodeCard from "@/components/Modules/TaskWorkflows/TaskWorkflowNodeCard";
import { ActionButton } from "@/components/Shared";
import { TaskWorkflow, TaskWorkflowNode } from "@/types/task-workflows";
import { ListTransition } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{
	taskWorkflow: TaskWorkflow;
}>();

const addNodeAction = dxTaskWorkflow.getAction("add-node");
const addConnectionAction = dxTaskWorkflow.getAction("add-connection");

const connectSourceNode = ref<TaskWorkflowNode>(null);
async function onTargetConnection(targetNode: TaskWorkflowNode) {
	await addConnectionAction.trigger(props.taskWorkflow, {
		source_node_id: connectSourceNode.value.id,
		target_node_id: targetNode.id
	});
	connectSourceNode.value = null;
	await refreshWorkflow();
}

function resolveNode(nodeId: string) {
	return props.taskWorkflow.nodes.find(node => node.id === nodeId);
}
async function refreshWorkflow() {
	await dxTaskWorkflow.routes.detailsAndStore(props.taskWorkflow);
}
</script>
