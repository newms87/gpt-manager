<template>
	<div class="task-process-agent-thread-card bg-transparent text-slate-300 flex flex-col flex-nowrap h-full">
		<div class="flex items-center flex-nowrap space-x-2">
			<div class="flex-grow">
				<AgentThreadResponseField v-model="agentResponse" />
			</div>
			<AiTokenUsageButton :usage="agentThread.usage" class="py-3 mr-3" />
			<ShowHideButton
				v-model="isShowingJobDispatch"
				:loading="isLoadingJobDispatch"
				:show-icon="JobDispatchIcon"
				color="gray"
				@show="refreshJobDispatch"
			/>
			<ActionButton
				:action="runAction"
				:input="agentThreadRunInput"
				:target="agentThread"
				:saving="agentThread.is_running"
				type="play"
				color="green-invert"
				label="Run"
			/>
			<ActionButton
				v-if="agentThread.is_running"
				:action="stopAction"
				:target="agentThread"
				type="pause"
				color="sky"
			/>
		</div>
		<div v-if="isShowingJobDispatch" class="mt-4">
			<JobDispatchCard v-if="agentThread.jobDispatch" :job="agentThread.jobDispatch" class="mb-8" />
			<QSkeleton v-else class="h-12" />
		</div>
		<div class="mt-4 flex-grow overflow-y-scroll -mr-10 pr-5">
			<ThreadMessageCard
				v-for="message in agentThread.messages"
				:key="message.id"
				:message="message"
				:thread="agentThread"
				class="mb-5"
			/>
			<ActionButton
				:saving="agentThread.is_running"
				:icon="CreateIcon"
				:action="createMessageAction"
				:target="agentThread"
				class="bg-sky-700 text-slate-200 text-lg w-full"
				label="Create Message"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import AgentThreadResponseField from "@/components/Modules/Agents/Fields/AgentThreadResponseField";
import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import { refreshAgentThread } from "@/components/Modules/Agents/Threads/store";
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import JobDispatchCard from "@/components/Modules/Audits/JobDispatches/JobDispatchCard";
import { AiTokenUsageButton } from "@/components/Shared";
import { AgentThread, AgentThreadResponseFormat } from "@/types";
import { FaRegularMessage as CreateIcon, FaSolidBusinessTime as JobDispatchIcon } from "danx-icon";
import { ActionButton, autoRefreshObject, ShowHideButton, stopAutoRefreshObject } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps<{
	agentThread: AgentThread;
}>();

const isShowingJobDispatch = ref(false);
const isLoadingJobDispatch = ref(false);
// Actions
const createMessageAction = dxAgentThread.getAction("create-message");
const runAction = dxAgentThread.getAction("run");
const stopAction = dxAgentThread.getAction("stop");

// TODO: Fill defaults from taskDefinitionAgent


const agentResponse = ref<AgentThreadResponseFormat>({
	format: "text",
	schema: null,
	fragment: null
});
const agentThreadRunInput = computed(() => {
	return {
		response_format: agentResponse.value.format,
		response_schema_id: agentResponse.value.schema?.id || null,
		response_fragment_id: agentResponse.value.fragment?.id || null
	};
});

async function refreshJobDispatch() {
	isLoadingJobDispatch.value = true;
	await dxAgentThread.routes.details(props.agentThread, {
		"*": false,
		jobDispatch: { logs: true, errors: true, apiLogs: true }
	});
	isLoadingJobDispatch.value = false;
}

let autoRefreshId = "agent-thread:" + props.agentThread.id;
onMounted(registerAutoRefresh);
onUnmounted(() => stopAutoRefreshObject(autoRefreshId));

function registerAutoRefresh() {
	autoRefreshObject(
		autoRefreshId,
		props.agentThread,
		(at: AgentThread) => console.log("checking agent", at.is_running) || at.is_running,
		refreshAgentThread
	);
}
</script>
