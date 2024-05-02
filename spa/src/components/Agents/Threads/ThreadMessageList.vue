<template>
	<div class="p-6">
		<ThreadMessageCard v-for="message in thread.messages" :key="message.id" :message="message" class="mb-5" />
		<QBtn
			class="bg-lime-800 text-slate-300 text-lg w-full"
			:loading="isSaving"
			:disable="isSaving"
			@click="onCreate"
		>
			<CreateIcon class="w-4 mr-3" />
			Create Message
		</QBtn>
	</div>
</template>
<script setup lang="ts">
import { performAction } from "@/components/Agents/Threads/threadActions";
import ThreadMessageCard from "@/components/Agents/Threads/ThreadMessageCard";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ref } from "vue";

const props = defineProps<{
	thread: AgentThread;
}>();

const isSaving = ref(false);
async function onCreate() {
	isSaving.value = true;
	await performAction("create-message", props.thread);
	isSaving.value = false;
}
</script>
