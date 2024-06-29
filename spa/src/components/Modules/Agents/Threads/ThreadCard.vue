<template>
	<div class="bg-transparent text-slate-300 flex flex-col flex-nowrap" :class="{'h-full': active}">
		<ThreadCardHeader v-model:active="active" v-model:logs="showLogs" :thread="thread" @close="$emit('close')" />
		<div v-if="showLogs" class="bg-slate-900 text-slate-400 rounded my-6 p-2">
			<div class="mb-3">
				<a
					v-if="thread.audit_request_id"
					target="_blank"
					:href="$router.resolve({path: `/audit-requests/${thread.audit_request_id}/errors`}).href"
				>
					View Errors
				</a>
			</div>
			<div v-for="(log, index) in thread.logs.split('\n') || ['(Logs Empty)']" :key="index">{{ log }}</div>
		</div>
		<div v-if="active" class="mt-4 flex-grow overflow-y-scroll -mr-10 pr-5">
			<ThreadMessageCard
				v-for="message in thread.messages"
				:key="message.id"
				:message="message"
				:thread="thread"
				class="mb-5"
			/>
		</div>

		<div v-if="active" class="mt-4">
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
import ActionButton from "@/components/Shared/Buttons/ActionButton";
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
