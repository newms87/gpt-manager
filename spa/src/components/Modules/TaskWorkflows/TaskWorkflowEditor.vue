<template>
	<div class="flex items-stretch flex-nowrap w-full h-full">
		<WorkflowCanvas
			v-if="taskWorkflow"
			:model-value="taskWorkflow"
			class="w-full h-full"
			@node-position="onNodePosition"
			@node-remove="workflowNode => removeNodeAction.trigger(workflowNode)"
			@connection-add="onConnectionAdd"
			@connection-remove="workflowConnection => removeConnectionAction.trigger(workflowConnection)"
		/>
		<WorkflowCanvasSidebar :task-workflow="taskWorkflow" />
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import { dxTaskWorkflowConnection } from "@/components/Modules/TaskWorkflows/TaskWorkflowConnections/config";
import { dxTaskWorkflowNode } from "@/components/Modules/TaskWorkflows/TaskWorkflowNodes/config";
import WorkflowCanvas from "@/components/Modules/WorkflowCanvas/WorkflowCanvas";
import WorkflowCanvasSidebar from "@/components/Modules/WorkflowCanvas/WorkflowCanvasSidebar";
import { TaskWorkflow, TaskWorkflowConnection, TaskWorkflowNode } from "@/types/task-workflows";

const props = defineProps<{
	taskWorkflow: TaskWorkflow;
}>();

const updateNodeAction = dxTaskWorkflowNode.getAction("update");
const removeNodeAction = dxTaskWorkflowNode.getAction("delete", { onFinish: refreshWorkflow });
const addConnectionAction = dxTaskWorkflow.getAction("add-connection", {
	onFinish: refreshWorkflow,
	optimistic: (action, target: TaskWorkflow, data: TaskWorkflowConnection) => target.connections.push({ ...data })
});
const removeConnectionAction = dxTaskWorkflowConnection.getAction("quick-delete", {
	onFinish: refreshWorkflow,
	optimisticDelete: true
});

async function refreshWorkflow() {
	await dxTaskWorkflow.routes.detailsAndStore(props.taskWorkflow);
}

async function onNodePosition(workflowNode: TaskWorkflowNode, position) {
	await updateNodeAction.trigger(workflowNode, { settings: position });
}

async function onConnectionAdd(connection: Partial<TaskWorkflowConnection>) {
	await addConnectionAction.trigger(props.taskWorkflow, connection);
}
</script>
