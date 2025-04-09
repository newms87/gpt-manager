<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<QSeparator class="bg-slate-400 my-4" />
		<AgentThreadInstructions />
		<TaskDefinitionAgentConfigField
			:task-definition="taskDefinition"
			:source-task-definitions="sourceTaskDefinitions"
		/>
		<TaskDefinitionDirectivesConfigField :task-definition="taskDefinition" class="mt-6" />
		<SchemaAndFragmentsConfigField class="mt-6" :task-definition="taskDefinition" />
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
import { AgentThreadInstructions } from "./Instructions";

defineProps<{
	taskDefinition: TaskDefinition;
	sourceTaskDefinitions?: TaskDefinition[];
}>();
</script>
