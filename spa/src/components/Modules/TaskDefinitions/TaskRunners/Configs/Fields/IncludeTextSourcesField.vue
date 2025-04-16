<template>
	<div class="include-text-sources-field flex-x gap-4">
		<div class="font-bold">Include Text Sources:</div>
		<QTabs
			v-model="includeSources"
			dense
			class="tab-buttons border-sky-900 w-48"
			indicator-color="sky-900"
			@update:model-value="onUpdate"
		>
			<QTab name="" label="Excluded" />
			<QTab name="1" label="Included" />
		</QTabs>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { ref } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const includeSources = ref(props.taskDefinition.task_runner_config?.include_text_sources || "");

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdate() {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...props.taskDefinition.task_runner_config,
			include_text_sources: includeSources.value
		}
	});
}
</script>
