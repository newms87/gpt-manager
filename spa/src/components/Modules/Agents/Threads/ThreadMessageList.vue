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
			<QBtn
				class="bg-sky-700 text-slate-200 text-lg flex-grow"
				:loading="createMessageAction.isApplying"
				@click="createMessageAction.trigger(thread)"
			>
				<CreateIcon class="w-4 mr-3" />
				Create Message
			</QBtn>
			<QBtn
				class="bg-lime-800 text-slate-300 text-lg ml-6 p-3"
				:disable="thread.messages.length === 0"
				:loading="runAction.isApplying || thread.is_running"
				@click="runAction.trigger(thread)"
			>
				<RunIcon class="w-4" />
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/Threads/threadActions";
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import { AgentThread } from "@/types/agents";
import { FaRegularMessage as CreateIcon, FaSolidPlay as RunIcon } from "danx-icon";

defineProps<{
	thread: AgentThread;
	readonly?: boolean
}>();

const createMessageAction = getAction("create-message");
const runAction = getAction("run");
</script>
