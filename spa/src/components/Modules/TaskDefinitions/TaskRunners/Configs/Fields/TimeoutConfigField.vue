<template>
	<div class="timeout-config">
		<NumberField
			:model-value="timeout"
			label="Timeout (seconds)"
			:min="1"
			:max="600"
			help="Maximum time allowed for task execution (1-600 seconds, default: 60)"
			@update:model-value="onUpdateTimeout"
		/>
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

const timeout = ref(props.taskDefinition.task_runner_config?.timeout || 60);

// Watch for changes in the task definition
watch(() => props.taskDefinition.task_runner_config?.timeout, (value) => {
	timeout.value = value || 60;
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdateTimeout(value: number) {
	timeout.value = value;
	updateConfig();
}

function updateConfig() {
	const updatedConfig = {
		...props.taskDefinition.task_runner_config,
		timeout: timeout.value
	};

	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: updatedConfig
	});
}
</script>