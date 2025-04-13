<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<SplitArtifactsInstructions />
		<SchemaAndFragmentsConfigField :task-definition="taskDefinition" class="p-4" :max-fragments="1" force-schema />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { SchemaAndFragmentsConfigField } from "./Fields";
import { SplitArtifactsInstructions } from "./Instructions";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
