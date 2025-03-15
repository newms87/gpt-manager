<template>
	<div class="bg-transparent text-slate-300 flex flex-col flex-nowrap" :class="{'h-full': active}">
		<AgentThreadCardHeader
			v-model:active="active"
			v-model:logs="showLogs"
			:thread="thread"
			@update:logs="loadJobDispatch"
			@close="$emit('close')"
		/>
		<div v-if="active" class="mt-4 flex-grow overflow-y-scroll -mr-10 pr-5">
			<JobDispatchCard v-if="showLogs && thread.jobDispatch" :job="thread.jobDispatch" class="mb-8" />

			<ThreadMessageCard
				v-for="message in thread.messages"
				:key="message.id"
				:message="message"
				:thread="thread"
				class="mb-5"
			/>
			<ActionButton
				:saving="thread.is_running"
				:icon="CreateIcon"
				:action="createMessageAction"
				:target="thread"
				class="bg-sky-700 text-slate-200 text-lg w-full"
				label="Create Message"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import AgentThreadCardHeader from "@/components/Modules/Agents/Threads/AgentThreadCardHeader";
import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import JobDispatchCard from "@/components/Modules/Audits/JobDispatches/JobDispatchCard";
import { AgentThread } from "@/types";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ActionButton } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["toggle", "close"]);
const props = defineProps<{
	thread: AgentThread;
}>();

const active = defineModel<boolean>("active", { default: false });
const showLogs = ref(false);
const createMessageAction = dxAgentThread.getAction("create-message");
function loadJobDispatch() {
	dxAgentThread.routes.details(props.thread, {
		"*": false,
		jobDispatch: { logs: true, errors: true, apiLogs: true }
	});
}
</script>
