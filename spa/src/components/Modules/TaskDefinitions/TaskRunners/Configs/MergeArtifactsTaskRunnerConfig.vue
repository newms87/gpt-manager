<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<FragmentSelectorConfigField :task-definition="taskDefinition" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { FragmentSelectorConfigField } from "./Fields";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
