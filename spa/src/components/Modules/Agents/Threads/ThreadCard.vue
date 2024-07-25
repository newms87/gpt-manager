<template>
	<div class="bg-transparent text-slate-300 flex flex-col flex-nowrap" :class="{'h-full': active}">
		<ThreadCardHeader v-model:active="active" v-model:logs="showLogs" :thread="thread" @close="$emit('close')" />
		<AuditRequestLogsCard
			v-if="showLogs"
			:audit-request-id="thread.audit_request_id"
			:logs="thread.logs"
			class="my-6"
		/>
		<div v-if="active" class="mt-4 flex-grow overflow-y-scroll -mr-10 pr-5">
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
import { getAction } from "@/components/Modules/Agents/Threads/threadActions";
import ThreadCardHeader from "@/components/Modules/Agents/Threads/ThreadCardHeader";
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import AuditRequestLogsCard from "@/components/Modules/Audits/AuditRequestLogs/AuditRequestLogsCard";
import { ActionButton } from "@/components/Shared";
import { AgentThread } from "@/types/agents";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ref } from "vue";

defineEmits(["toggle", "close"]);
defineProps<{
	thread: AgentThread;
}>();

const active = defineModel<boolean>("active", { default: false });
const showLogs = ref(false);
const createMessageAction = getAction("create-message");
</script>
