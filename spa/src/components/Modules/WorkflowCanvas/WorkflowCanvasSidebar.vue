<template>
	<CollapsableSidebar
		v-model:collapse="isCollapsed"
		class="workflow-canvas-sidebar"
		right-side
		min-width="3.5rem"
		name="workflow-canvas-sidebar"
	>
		<div class="sidebar-title text-center pt-4">
			<div
				v-if="isCollapsed"
				class="flex items-center justify-center w-full"
			>
				<ActionButton
					type="create"
					color="green"
					:action="addNodeAction"
					:target="taskWorkflow"
					class="p-2"
					icon-class="w-4"
					tooltip="Add Node to Workflow"
				/>
			</div>
			<div v-else class="text-xl">Add Tasks</div>
		</div>
		<div class="node-list">
			<div
				v-for="node in availableNodes"
				:key="node.type"
				class="node-item"
				draggable="true"
				@dragstart="onDragStart($event, node.type)"
			>
				<div class="node-item-icon" :class="node.icon">
					<div class="icon" v-html="node.icon.template" />
				</div>
				<div class="node-item-content">
					<div class="node-item-title">{{ node.type }}</div>
					<div class="node-item-description">{{ node.description }}</div>
				</div>
			</div>
		</div>
	</CollapsableSidebar>
</template>

<script setup lang="ts">
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows";
import { TaskWorkflow } from "@/types/task-workflows";
import { ActionButton, CollapsableSidebar } from "quasar-ui-danx";
import { ref, shallowRef } from "vue";

defineProps<{
	taskWorkflow: TaskWorkflow;
}>();

const isCollapsed = ref(false);
const addNodeAction = dxTaskWorkflow.getAction("add-node");

// Define available node types with descriptions
const availableNodes = shallowRef([]);

// Handle drag start event
const onDragStart = (event: DragEvent, nodeType: string) => {
	if (event.dataTransfer) {
		event.dataTransfer.setData("node-type", nodeType);
		event.dataTransfer.effectAllowed = "move";
	}
};
</script>

<style scoped>
.workflow-canvas-sidebar {

}
</style>
