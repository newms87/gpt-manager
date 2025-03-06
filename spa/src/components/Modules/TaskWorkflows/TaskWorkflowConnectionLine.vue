<template>
	<div class="py-2 px-4 border rounded-lg bg-slate-700">
		<div class="flex items-center">
			<LabelPillWidget :label="sourceNode.name" color="sky" size="sm" />
			<div class="flex-grow justify-center flex px-4">
				<ConnectedIcon class="w-7" />
			</div>
			<LabelPillWidget :label="targetNode.name" color="green" size="sm" />
			<ActionButton
				type="trash"
				color="white"
				:action="deleteNodeAction"
				:target="taskWorkflowConnection"
				class="ml-4"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflowConnection } from "@/components/Modules/TaskWorkflows/TaskWorkflowConnections/config";
import { TaskWorkflowConnection, TaskWorkflowNode } from "@/types/task-workflows";
import { FaSolidArrowRight as ConnectedIcon } from "danx-icon";
import { ActionButton, LabelPillWidget } from "quasar-ui-danx";

const emit = defineEmits(["delete"]);
defineProps<{
	taskWorkflowConnection: TaskWorkflowConnection;
	sourceNode: TaskWorkflowNode;
	targetNode: TaskWorkflowNode;
}>();

const deleteNodeAction = dxTaskWorkflowConnection.getAction("quick-delete", { onFinish: () => emit("delete") });
</script>
