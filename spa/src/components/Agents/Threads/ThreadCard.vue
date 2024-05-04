<template>
	<QCard class="bg-slate-400 text-slate-700">
		<QCardSection>
			<div class="flex items-center cursor-pointer" @click="$emit('open')">
				<h5 class="flex-grow">{{ thread.name }}</h5>
				<QBtn
					class="text-lime-800 bg-green-200 hover:bg-lime-800 hover:text-green-200 mr-6"
					:disable="runAction.isApplying"
					:loading="runAction.isApplying"
					@click.stop="runAction.trigger(thread)"
				>
					<RunIcon class="w-3 mr-2" />
					Run Thread
					({{ thread.messages.length }} messages)
				</QBtn>
				<QBtn
					class="text-red-900 hover:bg-red-300 shadow-none"
					:disable="deleteAction.isApplying"
					:loading="deleteAction.isApplying"
					@click.stop="onDelete"
				>
					<DeleteIcon class="w-4" />
				</QBtn>
			</div>
			<div v-if="!active && thread.summary" class="mt-2">{{ thread.summary }}</div>
			<div v-if="active" class="mt-4">
				<ThreadMessageList :thread="thread" />
			</div>
		</QCardSection>
	</QCard>
</template>

<script setup lang="ts">
import { AgentThread } from "@/components/Agents/agents";
import { getAction } from "@/components/Agents/Threads/threadActions";
import ThreadMessageList from "@/components/Agents/Threads/ThreadMessageList";
import { FaRegularTrashCan as DeleteIcon, FaSolidPlay as RunIcon } from "danx-icon";

const emit = defineEmits(["open", "close"]);
const props = defineProps<{
	thread: AgentThread;
	active?: boolean;
}>();

const runAction = getAction("run");
const deleteAction = getAction("delete");

async function onDelete() {
	const result = await deleteAction.trigger(props.thread);
	if (result?.success) {
		emit("close");
	}
}
</script>
