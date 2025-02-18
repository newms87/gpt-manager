<template>
	<div class="py-2 px-4 border rounded-lg bg-sky-900">
		<div class="flex items-center">
			<ActionButton
				v-if="targetingSourceNode && targetingSourceNode.id !== taskWorkflowNode.id"
				type="create"
				color="green-invert"
				class="mr-4"
				@click="emit('connect-target')"
			/>
			<div class="flex-grow">{{ taskWorkflowNode.name }}</div>
			<template v-if="!targetingSourceNode">
				<ActionButton type="trash" color="white" :action="deleteNodeAction" :target="taskWorkflowNode" class="ml-4" />
				<ActionButton type="create" color="sky" class="ml-4" @click="emit('connect-source')" />
			</template>
			<template v-else-if="taskWorkflowNode.id === targetingSourceNode.id">
				<ActionButton type="cancel" color="white" class="ml-4" @click="emit('cancel')" />
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflowNode } from "@/components/Modules/TaskWorkflows/TaskWorkflowNodes/config";
import { ActionButton } from "@/components/Shared";
import { TaskWorkflowNode } from "@/types/task-workflows";

const emit = defineEmits(["delete", "connect-source", "connect-target", "cancel"]);
defineProps<{
	taskWorkflowNode: TaskWorkflowNode;
	targetingSourceNode?: TaskWorkflowNode;
}>();

const deleteNodeAction = dxTaskWorkflowNode.getAction("quick-delete", { onFinish: () => emit("delete") });
</script>
