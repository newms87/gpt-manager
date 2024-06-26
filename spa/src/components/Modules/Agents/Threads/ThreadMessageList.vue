<template>
	<div>
		<ThreadMessageCard
			v-for="message in thread.messages"
			:key="message.id"
			:readonly="readonly"
			:message="message"
			:thread="thread"
			class="mb-5"
		/>
		<div v-if="!readonly" class="flex items-stretch">
			<ActionButton
				:saving="thread.is_running"
				:icon="CreateIcon"
				:action="createMessageAction"
				:target="thread"
				class="bg-sky-700 text-slate-200 text-lg flex-grow"
				label="Create Message"
			/>
			<ActionButton
				:action="thread.is_running ? stopAction : runAction"
				:target="thread"
				:type="thread.is_running ? 'pause' : 'play'"
				:color="thread.is_running ? 'sky' : 'green-invert'"
				class="text-lg ml-3 p-3"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/Threads/threadActions";
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { AgentThread } from "@/types/agents";
import { FaRegularMessage as CreateIcon } from "danx-icon";

defineProps<{
	thread: AgentThread;
	readonly?: boolean
}>();

const createMessageAction = getAction("create-message");
const runAction = getAction("run");
const stopAction = getAction("stop");
</script>
