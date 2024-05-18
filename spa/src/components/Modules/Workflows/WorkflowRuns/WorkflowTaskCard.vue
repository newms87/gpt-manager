<template>
	<div>
		<div class="flex items-center flex-nowrap">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-lg font-bold">Task ({{ task.id }})</div>
			</div>
			<div class="p-2 bg-slate-800 rounded-lg">{{ taskTimer }}</div>
		</div>
		<div>
			<div v-for="(logItem, index) in logItems" :key="task.id + '-' + index" class="p-2">
				{{ logItem }}
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { WorkflowTask } from "@/types/workflows";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps<{
	task: WorkflowTask;
}>();

const taskTimer = ref(calcTaskTimer());
onMounted(() => {
	const interval = setInterval(() => {
		taskTimer.value = calcTaskTimer();
	}, 1000);
	onUnmounted(() => clearInterval(interval));
});

function calcTaskTimer() {
	return fDuration(new Date().getTime() - new Date(props.task.started_at).getTime());
}

function fDuration(duration) {
	const seconds = Math.floor((duration / 1000) % 60);
	const minutes = Math.floor((duration / (1000 * 60)) % 60);
	const hours = Math.floor((duration / (1000 * 60 * 60)) % 24);
	const days = Math.floor(duration / (1000 * 60 * 60 * 24));

	return `${days}d ${hours}h ${minutes}m ${seconds}s`;
}

const logItems = computed(() => props.task.job_logs?.split("\n") || []);
</script>
