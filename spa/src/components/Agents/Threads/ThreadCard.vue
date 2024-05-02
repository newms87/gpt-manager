<template>
	<QCard class="bg-slate-400 text-slate-700">
		<QCardSection>
			<div class="flex items-center">
				<h5 class="flex-grow">{{ thread.name }}</h5>
				<div class="text-fuchsia-800">{{ thread.messages.length }} messages</div>
			</div>
			<div class="mt-2">{{ thread.summary }}</div>
		</QCardSection>
	</QCard>
</template>

<script setup lang="ts">
defineProps<{
	thread: AgentThread;
}>();
</script>
