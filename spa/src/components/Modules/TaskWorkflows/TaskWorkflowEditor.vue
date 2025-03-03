<template>
	<div class="flex items-stretch flex-nowrap w-full h-full">
		<WorkflowCanvas
			v-if="taskWorkflow"
			:model-value="taskWorkflow"
			class="w-full h-full"
			@node-position="onNodePosition"
			@node-remove="workflowNode => removeNodeAction.trigger(workflowNode)"
			@connection-add="onConnectionAdded"
		/>
		<ActionButton type="create" color="green" label="Add Node" :action="addNodeAction" :target="taskWorkflow" />
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import { dxTaskWorkflowNode } from "@/components/Modules/TaskWorkflows/TaskWorkflowNodes/config";
import WorkflowCanvas from "@/components/Modules/WorkflowCanvas/WorkflowCanvas";
import { ActionButton } from "@/components/Shared";
import { TaskWorkflow, TaskWorkflowNode } from "@/types/task-workflows";

const props = defineProps<{
	taskWorkflow: TaskWorkflow;
}>();

const addNodeAction = dxTaskWorkflow.getAction("add-node");
const updateNodeAction = dxTaskWorkflowNode.getAction("update");
const removeNodeAction = dxTaskWorkflowNode.getAction("delete", { onFinish: refreshWorkflow });
const addConnectionAction = dxTaskWorkflow.getAction("add-connection", { onFinish: refreshWorkflow });

async function refreshWorkflow() {
	await dxTaskWorkflow.routes.detailsAndStore(props.taskWorkflow);
}

async function onNodePosition(workflowNode: TaskWorkflowNode, position) {
	await updateNodeAction.trigger(workflowNode, { settings: position });
}

async function onConnectionAdded(connection) {
	await addConnectionAction.trigger(props.taskWorkflow, connection);
}
</script>
