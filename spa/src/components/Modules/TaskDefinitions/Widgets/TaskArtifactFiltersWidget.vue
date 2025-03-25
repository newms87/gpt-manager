<template>
	<div class="bg-sky-950 p-4 rounded">
		<div>Source Artifact Filters</div>
		<TaskArtifactFilterForm
			v-for="sourceTaskDefinition in sourceTaskDefinitions"
			:key="sourceTaskDefinition.id"
			:source-task-definition="sourceTaskDefinition"
			:target-task-definition="targetTaskDefinition"
			:task-artifact-filter="findTaskArtifactFilter(sourceTaskDefinition)"
		/>
	</div>
</template>
<script setup lang="ts">
import TaskArtifactFilterForm from "@/components/Modules/TaskDefinitions/Widgets/TaskArtifactFilterForm";
import { TaskDefinition } from "@/types";

const props = defineProps<{
	targetTaskDefinition: TaskDefinition;
	sourceTaskDefinitions: TaskDefinition[];
}>();

function findTaskArtifactFilter(sourceTaskDefinition: TaskDefinition) {
	return props.targetTaskDefinition.taskArtifactFiltersAsTarget.find(f => f.source_task_definition_id === sourceTaskDefinition.id);
}
</script>
