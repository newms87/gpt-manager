<template>
	<div>
		Task Run: {{ taskRun.id }}
	</div>
</template>
<script setup lang="ts">
import { routes } from "@/components/Modules/TaskDefinitions/TaskRuns/config/routes";
import { TaskRun } from "@/types/task-definitions";
import { onMounted, ref } from "vue";

const props = defineProps<{
	taskRun: TaskRun;
}>();

const isLoading = ref(false);
onMounted(loadTaskInput);

async function loadTaskInput() {
	isLoading.value = true;
	await routes.detailsAndStore(props.taskRun);
	isLoading.value = false;
}
</script>
