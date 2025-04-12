<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-1">
			<div class="text-xl font-medium mb-4">Filter Artifacts Configuration</div>
			<div class="text-sm text-slate-600 mb-4">
				Configure conditions to filter artifacts based on their content. You can create complex filter rules using AND/OR logic
				and various comparison operators.
			</div>
			
			<FilterConditionsField :task-definition="taskDefinition" />
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { PropType } from "vue";
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig.vue";
import { FilterConditionsField } from "./Fields";

defineProps({
  taskDefinition: {
    type: Object as PropType<TaskDefinition>,
    required: true
  }
});
</script>
