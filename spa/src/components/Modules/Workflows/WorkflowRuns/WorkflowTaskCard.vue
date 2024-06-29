<template>
	<div class="bg-slate-600 rounded-lg overflow-hidden">
		<div class="flex items-center flex-nowrap pr-2">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-xs py-6 px-2 bg-gray-700">Task ({{ task.id }})</div>
				<div>
					<div class="text-lg font-bold px-2 text-ellipsis">{{ task.job_name }}</div>
					<div class="flex items-center flex-nowrap px-2">
						<div class="bg-slate-400 text-slate-800 text-sm px-2 py-1 rounded-lg text-no-wrap">
							Group: {{ task.group || "N/A" }}
						</div>
						<div class="text-sm text-slate-400 ml-3 text-no-wrap">by {{ task.agent_name }}</div>
					</div>
				</div>
			</div>
			<ShowHideButton v-if="task.thread" v-model="showThread" label="Thread" class="bg-sky-800 mx-1 text-sm" />
			<ShowHideButton v-model="showLogs" label="Logs" class="bg-slate-800 mx-1 text-sm" />
			<ElapsedTimePill :start="task.started_at" :end="task.failed_at || task.completed_at" class="mx-1" />
			<div class="py-1 px-3 rounded-xl w-24 text-center ml-1" :class="workflowTaskStatus.classAlt">
				{{ task.status }}
			</div>
			<div class="ml-2">
				<AiTokenUsageButton :usage="task.usage" />
			</div>
		</div>
		<div v-if="showLogs" class="p-2">
			<div v-for="(logItem, index) in logItems" :key="task.id + '-' + index" class="p-2">
				{{ logItem }}
			</div>
		</div>
		<div v-if="showThread" class="p-2">
			<ThreadMessageCard
				v-for="message in task.thread.messages"
				:key="message.id"
				readonly
				:message="message"
				:thread="task.thread"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
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
