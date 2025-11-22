<template>
	<div class="task-process-agent-thread-card bg-transparent text-slate-300 flex flex-col flex-nowrap h-full">
		<div class="flex items-center justify-end flex-nowrap space-x-2">
			<AiTokenUsageButton v-if="agentThread.usage" :usage="agentThread.usage" class="py-3" />
			<ShowHideButton
				v-model="isShowingJobDispatch"
				:loading="isLoadingJobDispatch"
				:show-icon="JobDispatchIcon"
				color="gray"
				@show="refreshJobDispatch"
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
				:readonly="!agentThread.can.edit"
				class="mb-5"
			/>
			<div class="flex-x space-x-4">
				<AgentThreadResponseField v-model="agentResponse" class="flex-grow" />
				<ActionButton
					:saving="agentThread.is_running"
					:disabled="agentThread.is_running"
					:icon="CreateIcon"
					:action="createMessageAction"
					:target="agentThread"
					color="sky"
				/>
				<Transition mode="out-in">
					<ActionButton
						v-if="!agentThread.is_running"
						:action="runAction"
						:input="agentThreadRunInput"
						:target="agentThread"
						:saving="agentThread.is_running"
						type="play"
						color="green"
					/>
					<ActionButton
						v-else
						:action="stopAction"
						:target="agentThread"
						type="stop"
						color="red"
					/>
				</Transition>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { AgentThreadResponseField } from "@/components/Modules/Agents/Fields";
import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import { refreshAgentThread } from "@/components/Modules/Agents/Threads/store";
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import JobDispatchCard from "@/components/Modules/Audits/JobDispatches/JobDispatchCard";
import { AiTokenUsageButton } from "@/components/Shared";
import { usePusher } from "@/helpers/pusher";
import { AgentThread, AgentThreadResponseFormat, AgentThreadRun } from "@/types";
import { FaRegularMessage as CreateIcon, FaSolidBusinessTime as JobDispatchIcon } from "danx-icon";
import { ActionButton, ShowHideButton } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const props = defineProps<{
	agentThread: AgentThread;
}>();

const isShowingJobDispatch = ref(false);
const isLoadingJobDispatch = ref(false);
// Actions
const createMessageAction = dxAgentThread.getAction("create-message");
const runAction = dxAgentThread.getAction("run");
const stopAction = dxAgentThread.getAction("stop");

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

onMounted(() => {
	usePusher().onEvent("AgentThreadRun", "updated", async (data: AgentThreadRun) => {
		if (data.agent_thread_id === props.agentThread.id) {
			await refreshAgentThread(props.agentThread);
		}
	});
});
</script>
