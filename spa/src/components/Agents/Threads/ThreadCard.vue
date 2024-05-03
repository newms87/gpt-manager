<template>
	<QCard class="bg-slate-400 text-slate-700">
		<QCardSection>
			<div class="flex items-center cursor-pointer" @click="$emit('open')">
				<h5 class="flex-grow">{{ thread.name }}</h5>
				<div class="text-lime-800 mr-6">{{ thread.messages.length }} messages</div>
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
import { FaRegularTrashCan as DeleteIcon } from "danx-icon";
import { ref } from "vue";

const emit = defineEmits(["open", "close"]);
const props = defineProps<{
	thread: AgentThread;
	active?: boolean;
}>();

const isDeleting = ref(false);
async function onDelete() {
	isDeleting.value = true;
	await performAction("delete", props.thread);
	isDeleting.value = false;
	emit("close");
}
</script>
