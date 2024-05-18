<template>
	<div class="bg-slate-500 px-4 py-1 rounded-lg">
		<div class="flex items-center flex-nowrap">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-lg font-bold">Task ({{ task.id }})</div>
				<QBtn class="ml-4 bg-sky-800 py-1 px-2" @click="showLogs = !showLogs">
					<template v-if="showLogs">
						<HideIcon class="w-4 mr-2" />
						Hide Logs
					</template>
					<template v-else>
						<ShowIcon class="w-4 mr-2" />
						View Logs
					</template>
				</QBtn>
			</div>
			<div class="py-1 px-3 bg-slate-800 rounded-lg text-xs mr-4 w-32 text-center">{{ taskTimer }}</div>
			<div class="py-1 px-3 rounded-xl w-24 text-center" :class="workflowTaskStatus.classAlt">{{ task.status }}</div>
		</div>
		<div v-if="showLogs">
			<div v-for="(logItem, index) in logItems" :key="task.id + '-' + index" class="p-2">
				{{ logItem }}
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { WorkflowTask } from "@/types/workflows";
import { FaSolidEye as ShowIcon, FaSolidEyeSlash as HideIcon } from "danx-icon";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps<{
	task: WorkflowTask;
}>();

const workflowTaskStatus = computed(() => WORKFLOW_STATUS.resolve(props.task.status));
const showLogs = ref(false);

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
