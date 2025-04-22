<template>
	<div class="task-artifact-filters-widget">
		<h3 class="text-lg font-medium mb-3">{{ title }}</h3>
		<template v-if="!targetTaskDefinition.taskArtifactFiltersAsTarget">
			<QSkeleton v-for="i in 3" :key="i" class="mb-2 h-8 w-full" />
		</template>
		<template v-else>
			<TaskArtifactFilterForm
				v-for="sourceTaskDefinition in sourceTaskDefinitions"
				:key="sourceTaskDefinition.id"
				:source-task-definition="sourceTaskDefinition"
				:target-task-definition="targetTaskDefinition"
				:task-artifact-filter="findTaskArtifactFilter(sourceTaskDefinition)"
				class="p-2 mb-2"
			/>
		</template>
	</div>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import type { TaskDefinition } from "@/types";
import { onMounted } from "vue";
import TaskArtifactFilterForm from "./TaskArtifactFiltersFieldForm.vue";

const props = withDefaults(defineProps<{
	title?: string;
	targetTaskDefinition: TaskDefinition
	sourceTaskDefinitions: TaskDefinition[]
}>(), {
	title: "Source Artifact Filters"
});

/**
 * Find a task artifact filter for the given source task definition
 * Returns undefined if no filter exists
 */
function findTaskArtifactFilter(sourceTaskDefinition: TaskDefinition) {
	return props.targetTaskDefinition.taskArtifactFiltersAsTarget?.find(
		f => f.source_task_definition_id === sourceTaskDefinition.id
	);
}

// Fetch taskArtifactFiltersAsTarget from the backend
onMounted(async () => {
	await dxTaskDefinition.routes.details(props.targetTaskDefinition, {
		taskArtifactFiltersAsTarget: true
	});
});
</script>
