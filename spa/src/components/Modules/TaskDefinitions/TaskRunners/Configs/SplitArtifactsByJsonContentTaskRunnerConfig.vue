<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<SplitArtifactsByJsonContentInstructions />
		<SchemaAndFragmentsConfig :task-definition="taskDefinition" class="p-4" :max-fragments="1" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import SplitArtifactsByJsonContentInstructions
	from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Instructions/SplitArtifactsByJsonContentInstructions";
import SchemaAndFragmentsConfig
	from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/SchemaAndFragmentsConfig";
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
