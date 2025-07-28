<template>
	<div class="context-window-config">
		<h6 class="text-lg font-semibold mb-2">Context Window Settings</h6>
		<div class="text-xs text-gray-600 mb-4">
			Additional artifacts shown for context to improve classification accuracy and detect document continuity
		</div>
		<div class="flex gap-4">
			<NumberField
				:model-value="contextBefore"
				label="Artifacts Before"
				:min="0"
				:max="10"
				help="Previous pages shown as context"
				class="flex-1"
				@update:model-value="onUpdateContextBefore"
			/>
			<NumberField
				:model-value="contextAfter"
				label="Artifacts After"
				:min="0"
				:max="10"
				help="Following pages shown as context"
				class="flex-1"
				@update:model-value="onUpdateContextAfter"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { NumberField } from "quasar-ui-danx";
import { TaskDefinition } from "@/types";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const contextBefore = ref(props.taskDefinition.task_runner_config?.context_before || 0);
const contextAfter = ref(props.taskDefinition.task_runner_config?.context_after || 0);

// Watch for changes in the task definition
watch(() => props.taskDefinition.task_runner_config?.context_before, (value) => {
	contextBefore.value = value || 0;
});

watch(() => props.taskDefinition.task_runner_config?.context_after, (value) => {
	contextAfter.value = value || 0;
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdateContextBefore(value: number) {
	contextBefore.value = value;
	updateConfig();
}

function onUpdateContextAfter(value: number) {
	contextAfter.value = value;
	updateConfig();
}

function updateConfig() {
	const updatedConfig = { ...props.taskDefinition.task_runner_config };
	
	// Only set values if they're greater than 0, otherwise remove them
	if (contextBefore.value > 0) {
		updatedConfig.context_before = contextBefore.value;
	} else {
		delete updatedConfig.context_before;
	}
	
	if (contextAfter.value > 0) {
		updatedConfig.context_after = contextAfter.value;
	} else {
		delete updatedConfig.context_after;
	}

	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: updatedConfig
	});
}
</script>