<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<TaskDefinitionAgentConfigField :task-definition="taskDefinition" />
		<TaskDefinitionDirectivesConfigField :task-definition="taskDefinition" class="mt-6" />
		<SchemaAndFragmentsConfigField class="mt-6" :task-definition="taskDefinition" :max-fragments="1" force-schema />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import {
	SchemaAndFragmentsConfigField,
	TaskDefinitionAgentConfigField,
	TaskDefinitionDirectivesConfigField
} from "./Fields";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
