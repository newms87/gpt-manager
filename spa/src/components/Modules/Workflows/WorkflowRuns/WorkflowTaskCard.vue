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
			<ElapsedTimePill :start="task.started_at" :end="task.failed_at || task.completed_at" />
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
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import { WorkflowTask } from "@/types/workflows";
import { FaSolidEye as ShowIcon, FaSolidEyeSlash as HideIcon } from "danx-icon";
import { computed, ref } from "vue";

const props = defineProps<{
	task: WorkflowTask;
}>();

const workflowTaskStatus = computed(() => WORKFLOW_STATUS.resolve(props.task.status));
const showLogs = ref(false);

const logItems = computed(() => props.task.job_logs?.split("\n") || []);
</script>
