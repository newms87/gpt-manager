<template>
	<div>
		<div class="flex items-center">
			<div class="bg-sky-900 text-sky-400 px-2 py-1 rounded-full text-xs">{{ taskRun.id }}</div>
		</div>
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
