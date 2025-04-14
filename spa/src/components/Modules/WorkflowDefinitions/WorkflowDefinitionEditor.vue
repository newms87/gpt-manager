<template>
	<div class="flex items-stretch flex-nowrap w-full h-full">
		<WorkflowCanvas
			v-if="activeWorkflowDefinition"
			:model-value="activeWorkflowDefinition"
			:workflow-run="activeWorkflowRun"
			:loading="isCreatingWorkflowRun"
			class="w-full h-full"
			@node-position="onNodePosition"
			@node-copy="node => copyNodeAction.trigger(node)"
			@node-edit="node => nodeToEdit = node"
			@node-remove="workflowNode => removeNodeAction.trigger(workflowNode)"
			@connection-add="onConnectionAdd"
			@connection-remove="workflowConnection => removeConnectionAction.trigger(workflowConnection)"
		/>
		<WorkflowCanvasSidebar :workflow-definition="activeWorkflowDefinition" @refresh="refreshActiveWorkflowDefinition" />
		<TaskDefinitionConfigDialog
			v-if="nodeToEdit?.taskDefinition"
			:task-definition="nodeToEdit.taskDefinition"
			:workflow-node="nodeToEdit"
			@close="nodeToEdit = null"
		/>
	</div>
</template>
<script setup lang="ts">
import { TaskDefinitionConfigDialog } from "@/components/Modules/TaskDefinitions";
import { loadTaskDefinitions } from "@/components/Modules/WorkflowCanvas/helpers";
import WorkflowCanvas from "@/components/Modules/WorkflowCanvas/WorkflowCanvas";
import WorkflowCanvasSidebar from "@/components/Modules/WorkflowCanvas/WorkflowCanvasSidebar";
import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import {
	activeWorkflowDefinition,
	activeWorkflowRun,
	isCreatingWorkflowRun,
	refreshActiveWorkflowDefinition
} from "@/components/Modules/WorkflowDefinitions/store";
import { dxWorkflowConnection } from "@/components/Modules/WorkflowDefinitions/WorkflowConnections/config";
import { dxWorkflowNode } from "@/components/Modules/WorkflowDefinitions/WorkflowNodes/config";
import { WorkflowConnection, WorkflowDefinition, WorkflowNode } from "@/types";
import { ref } from "vue";

const nodeToEdit = ref<WorkflowNode>(null);

const copyNodeAction = dxWorkflowNode.getAction("copy", { onFinish: () => Promise.all([refreshActiveWorkflowDefinition(), loadTaskDefinitions()]) });
const updateNodeAction = dxWorkflowNode.getAction("update");
const removeNodeAction = dxWorkflowNode.getAction("quick-delete", {
	onFinish: refreshActiveWorkflowDefinition,
	optimisticDelete: true
});
const addConnectionAction = dxWorkflowDefinition.getAction("add-connection", {
	onFinish: refreshActiveWorkflowDefinition,
	optimistic: (action, target: WorkflowDefinition, data: WorkflowConnection) => target.connections.push({ ...data })
});
const removeConnectionAction = dxWorkflowConnection.getAction("quick-delete", {
	onFinish: refreshActiveWorkflowDefinition,
	optimisticDelete: true
});

async function onNodePosition(workflowNode: WorkflowNode, position) {
	await updateNodeAction.trigger(workflowNode, { settings: position });
}

async function onConnectionAdd(connection: Partial<WorkflowConnection>) {
	await addConnectionAction.trigger(activeWorkflowDefinition.value, connection);
}
</script>
