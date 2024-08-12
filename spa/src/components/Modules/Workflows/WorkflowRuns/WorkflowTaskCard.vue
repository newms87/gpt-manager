<template>
	<div class="bg-slate-600 rounded-lg overflow-hidden">
		<div class="flex items-center flex-nowrap pr-2">
			<div class="flex-grow flex-nowrap flex items-stretch">
				<div class="text-xs px-2 bg-gray-700 flex items-center">Task ({{ task.id }})</div>
				<div class="p-2 max-w-96">
					<div class="text-lg font-bold text-ellipsis">{{ task.job_name }}</div>
					<div class="flex items-center flex-nowrap overflow-hidden">
						<div class="bg-slate-400 text-slate-800 text-sm px-2 py-1 rounded-lg max-w-full overflow-ellipsis overflow-hidden">
							<div v-for="groupItem in groupItems" :key="groupItem.groupKey">
								{{ groupItem.groupValue || groupItem.groupKey }}
								<QTooltip v-if="groupItem.groupValue">{{ groupItem.groupKey }}</QTooltip>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div v-if="task.agent_name" class="mx-4">
				<div class="text-sm text-slate-400 text-no-wrap">
					<a :href="agentUrl(task.agent_id)" target="_blank">
						by {{ task.agent_name }}
					</a>
				</div>
				<div class="text-sm text-slate-400 bg-slate-800 px-3 py-1 mt-1 rounded-full text-no-wrap inline-block">{{
						task.model
					}}
				</div>
			</div>
			<ShowHideButton v-if="task.thread" v-model="showThread" label="Thread" class="bg-sky-800 mx-1 text-sm" />
			<ShowHideButton v-model="showLogs" label="Logs" class="bg-slate-800 mx-1 text-sm" />
			<WorkflowStatusTimerPill :runner="task" inverse class="mx-1" padding="py-1" />
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
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import { AiTokenUsageButton, ShowHideButton } from "@/components/Shared";
import router from "@/router";
import { WorkflowTask } from "@/types/workflows";
import { computed, ref } from "vue";

const props = defineProps<{
	task: WorkflowTask;
}>();

const showLogs = ref(false);
const showThread = ref(false);

const groupItems = computed(() => {
	return props.task.group.split(",").map((groupItem) => {
		const [groupKey, groupValue] = groupItem.match(":") ? groupItem.split(":") : groupItem.split("#");
		return { groupKey, groupValue };
	});
});

function agentUrl(id) {
	return router.resolve({ name: "agents", params: { id } }).href;
}
</script>
