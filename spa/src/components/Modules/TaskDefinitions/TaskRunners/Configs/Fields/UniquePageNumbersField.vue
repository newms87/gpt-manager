<template>
	<div class="unique-page-numbers-field flex-x gap-4">
		<div class="font-bold">Unique Page Numbers:</div>
		<QTabs
			v-model="uniquePageNumbers"
			dense
			class="tab-buttons border-sky-900 w-48"
			indicator-color="sky-900"
			@update:model-value="onUpdate"
		>
			<QTab name="" label="Disabled" />
			<QTab name="1" label="Enabled" />
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

const uniquePageNumbers = ref(props.taskDefinition.task_runner_config?.unique_page_numbers || "");

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdate() {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...props.taskDefinition.task_runner_config,
			unique_page_numbers: uniquePageNumbers.value
		}
	});
}
</script>