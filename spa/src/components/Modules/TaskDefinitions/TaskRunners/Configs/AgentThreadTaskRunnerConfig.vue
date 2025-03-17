<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<TaskDefinitionAgentList class="mt-8" :task-definition="taskDefinition" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import TaskDefinitionAgentList from "@/components/Modules/TaskDefinitions/TaskDefinitionAgents/TaskDefinitionAgentList";
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
