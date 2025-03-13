<template>
	<div class="task-process-agent-thread-card bg-transparent text-slate-300 flex flex-col flex-nowrap h-full">
		<div class="flex items-center flex-nowrap space-x-2">
			<div class="flex-grow">
				<AgentThreadResponseField v-model="agentResponse" />
			</div>
			<AiTokenUsageButton :usage="agentThread.usage" class="py-3 mr-3" />
			<ActionButton
				:action="runAction"
				:input="agentThreadRunInput"
				:target="agentThread"
				:saving="agentThread.is_running"
				type="play"
				color="green-invert"
				label="Run"
				class="mr-3 px-3"
			/>
			<ActionButton
				v-if="agentThread.is_running"
				:action="stopAction"
				:target="agentThread"
				type="pause"
				color="sky"
				class="p-3"
			/>
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
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import { AiTokenUsageButton } from "@/components/Shared";
import { AgentThread, AgentThreadResponseFormat } from "@/types";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineProps<{
	agentThread: AgentThread;
}>();

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
</script>
