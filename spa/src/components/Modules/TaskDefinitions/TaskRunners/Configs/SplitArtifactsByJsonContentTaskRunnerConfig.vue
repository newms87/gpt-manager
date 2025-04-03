<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<SplitArtifactsByJsonContentInstructions />
		<SchemaAndFragmentsConfigField :task-definition="taskDefinition" class="p-4" :max-fragments="1" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { SchemaAndFragmentsConfigField } from "./Fields";
import { SplitArtifactsByJsonContentInstructions } from "./Instructions";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
