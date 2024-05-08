<template>
	<div>
		<ThreadMessageCard v-for="message in thread.messages" :key="message.id" :message="message" class="mb-5" />
		<div class="flex items-stretch">
			<QBtn
				class="bg-sky-700 text-slate-200 text-lg flex-grow"
				:loading="createMessageAction.isApplying"
				:disable="createMessageAction.isApplying"
				@click="createMessageAction.trigger(thread)"
			>
				<CreateIcon class="w-4 mr-3" />
				Create Message
			</QBtn>
			<QBtn
				class="bg-lime-800 text-slate-300 text-lg ml-6 p-3"
				:disable="runAction.isApplying || thread.messages.length === 0"
				:loading="runAction.isApplying"
				@click="runAction.trigger(thread)"
			>
				<RunIcon class="w-4" />
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { AgentThread } from "@/components/Agents/agents";
import { getAction } from "@/components/Agents/Threads/threadActions";
import ThreadMessageCard from "@/components/Agents/Threads/ThreadMessageCard";
import { FaRegularMessage as CreateIcon, FaSolidPlay as RunIcon } from "danx-icon";

defineProps<{
	thread: AgentThread;
}>();

const createMessageAction = getAction("create-message");
const runAction = getAction("run");
</script>
