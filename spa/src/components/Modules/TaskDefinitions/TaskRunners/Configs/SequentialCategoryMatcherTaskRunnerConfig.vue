<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<TaskDefinitionAgentConfigField
			:task-definition="taskDefinition"
			:source-task-definitions="sourceTaskDefinitions"
		/>
		<TaskDefinitionDirectivesConfigField :task-definition="taskDefinition" class="mt-6" />
		<SchemaAndFragmentsConfigField :task-definition="taskDefinition" :max-fragments="1" force-schema class="mt-6" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { SchemaAndFragmentsConfigField } from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Fields";
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { TaskDefinitionAgentConfigField, TaskDefinitionDirectivesConfigField } from "./Fields";

defineProps<{
	taskDefinition: TaskDefinition;
	sourceTaskDefinitions?: TaskDefinition[];
}>();
</script>
