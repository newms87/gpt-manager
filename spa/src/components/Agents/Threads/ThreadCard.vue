<template>
	<QCard class="bg-slate-400 text-slate-700">
		<QCardSection>
			<div class="flex items-center cursor-pointer" @click="$emit('open')">
				<h5 class="flex-grow">{{ thread.name }}</h5>
				<QBtn
					class="text-lime-800 bg-green-200 hover:bg-lime-800 hover:text-green-200 mr-6"
					:disable="isRunning"
					:loading="isRunning"
					@click.stop="onRun"
				>
					<RunIcon class="w-3 mr-2" />
					Run Thread
					({{ thread.messages.length }} messages)
				</QBtn>
				<QBtn
					class="text-red-900 hover:bg-red-300 shadow-none"
					:disable="isDeleting"
					:loading="isDeleting"
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
import { performAction } from "@/components/Agents/Threads/threadActions";
import ThreadMessageList from "@/components/Agents/Threads/ThreadMessageList";
import { FaRegularTrashCan as DeleteIcon, FaSolidPlay as RunIcon } from "danx-icon";
import { ref } from "vue";

const emit = defineEmits(["open", "close"]);
const props = defineProps<{
	thread: AgentThread;
	active?: boolean;
}>();

const isRunning = ref(false);
async function onRun() {
	isRunning.value = true;
	await performAction("run", props.thread);
	isRunning.value = false;
}
const isDeleting = ref(false);
async function onDelete() {
	isDeleting.value = true;
	const result = await performAction("delete", props.thread);
	isDeleting.value = false;
	if (result?.success) {
		emit("close");
	}
}
</script>
