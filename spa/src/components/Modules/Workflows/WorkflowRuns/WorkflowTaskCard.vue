<template>
	<div class="bg-slate-500 px-4 py-1 rounded-lg">
		<div class="flex items-center flex-nowrap">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-lg font-bold">Task ({{ task.agent_name }})</div>
			</div>
			<ShowHideButton v-if="task.thread" v-model="showThread" label="Thread" class="bg-sky-800 mx-1 text-sm" />
			<ShowHideButton v-model="showLogs" label="Logs" class="bg-slate-800 mx-1 text-sm" />
			<ElapsedTimePill :start="task.started_at" :end="task.failed_at || task.completed_at" class="mx-1" />
			<div class="py-1 px-3 rounded-xl w-24 text-center ml-1" :class="workflowTaskStatus.classAlt">{{
					task.status
				}}
			</div>
		</div>
		<div v-if="showLogs">
			<div v-for="(logItem, index) in logItems" :key="task.id + '-' + index" class="p-2">
				{{ logItem }}
			</div>
		</div>
		<div v-if="showThread">
			<ThreadCard :thread="task.thread" />
		</div>
	</div>
</template>
<script setup lang="ts">
import ThreadCard from "@/components/Modules/Agents/Threads/ThreadCard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import { WorkflowTask } from "@/types/workflows";
import { computed, ref } from "vue";

const props = defineProps<{
	task: WorkflowTask;
}>();

const workflowTaskStatus = computed(() => WORKFLOW_STATUS.resolve(props.task.status));
const showLogs = ref(false);
const showThread = ref(false);

const logItems = computed(() => props.task.job_logs?.split("\n") || []);
</script>
