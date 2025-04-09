<template>
	<div class="task-artifact-filters-widget">
		<div>Source Artifact Filters</div>
		<TaskArtifactFilterForm
			v-for="sourceTaskDefinition in sourceTaskDefinitions"
			:key="sourceTaskDefinition.id"
			:source-task-definition="sourceTaskDefinition"
			:target-task-definition="targetTaskDefinition"
			:task-artifact-filter="findTaskArtifactFilter(sourceTaskDefinition)"
			class="p-2"
		/>
	</div>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import TaskArtifactFilterForm from "./TaskArtifactFiltersFieldForm";

const props = defineProps<{
	targetTaskDefinition: TaskDefinition;
	sourceTaskDefinitions: TaskDefinition[];
}>();

function findTaskArtifactFilter(sourceTaskDefinition: TaskDefinition) {
	return props.targetTaskDefinition.taskArtifactFiltersAsTarget.find(f => f.source_task_definition_id === sourceTaskDefinition.id);
}
</script>
