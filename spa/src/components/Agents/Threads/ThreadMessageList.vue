<template>
	<div class="p-6">
		<ThreadMessageCard v-for="message in thread.messages" :key="message.id" :message="message" />
	</div>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Agents/Threads/ThreadMessageCard";

defineProps<{
	thread: AgentThread;
}>();
</script>
