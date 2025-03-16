<template>
	<div class="relative">
		<slot>
			<TaskDefinitionAgentList class="mt-8" :task-definition="taskDefinition" />
		</slot>
	</div>
</template>
<script setup lang="ts">
import TaskDefinitionAgentList from "@/components/Modules/TaskDefinitions/TaskDefinitionAgents/TaskDefinitionAgentList";
import { TaskDefinition } from "@/types";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
