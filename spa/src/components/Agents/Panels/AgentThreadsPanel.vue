<template>
	<div class="p-6">
		<h4 v-if="!agent?.threads.length" class="text-center mt-10">This agent has not generated any threads</h4>
		<template v-else>
			<ThreadCard v-for="thread in agent.threads" :key="thread.id" :thread="thread" class="mb-4" />
		</template>
	</div>
</template>
<script setup lang="ts">

import ThreadCard from "@/components/Agents/Threads/ThreadCard";

defineProps<{
	agent: Agent,
}>();
</script>
