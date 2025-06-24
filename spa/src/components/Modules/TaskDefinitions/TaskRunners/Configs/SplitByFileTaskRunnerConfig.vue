<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-4">
			<div class="text-sm text-gray-500 mb-4">
				This task runner splits input artifacts by their files. Each file will become a separate artifact.
			</div>
			<UniquePageNumbersField :task-definition="taskDefinition" />
		</div>
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { UniquePageNumbersField } from "./Fields";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>