<template>
	<div class="bg-slate-600 rounded-lg overflow-hidden">
		<div class="flex items-center flex-nowrap pr-2">
			<div class="flex-grow flex-nowrap flex items-stretch">
				<div class="text-xs px-2 bg-gray-700 flex items-center">Task ({{ task.id }})</div>
				<div class="p-2 max-w-96">
					<div class="text-lg font-bold text-ellipsis">{{ task.job_name }}</div>
					<div class="flex items-center flex-nowrap">
						<div class="bg-slate-400 text-slate-800 text-sm px-2 py-1 rounded-lg">
							{{ task.group || "N/A" }}
						</div>
					</div>
				</div>
			</div>
			<div v-if="task.agent_name" class="mx-4">
				<div class="text-sm text-slate-400 text-no-wrap">by {{ task.agent_name }}</div>
				<div class="text-sm text-slate-400 bg-slate-800 px-3 py-1 mt-1 rounded-full text-no-wrap inline-block">{{
						task.model
					}}
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
		<AuditRequestLogsCard v-if="showLogs" :audit-request-id="task.audit_request_id" :logs="task.logs" />
		<div v-if="showThread" class="px-2">
			<ThreadMessageCard
				v-for="message in task.thread.messages"
				:key="message.id"
				readonly
				:message="message"
				:thread="task.thread"
				class="my-3"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import AuditRequestLogsCard from "@/components/Modules/Audits/AuditRequestLogs/AuditRequestLogsCard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import { AiTokenUsageButton, ShowHideButton } from "@/components/Shared";
import { WorkflowTask } from "@/types/workflows";
import { computed, ref } from "vue";

const props = defineProps<{
	task: WorkflowTask;
}>();

const workflowTaskStatus = computed(() => WORKFLOW_STATUS.resolve(props.task.status));
const showLogs = ref(false);
const showThread = ref(false);
</script>
