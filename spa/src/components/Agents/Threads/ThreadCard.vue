<template>
	<QCard class="bg-slate-400 text-slate-700">
		<QCardSection>
			<div class="flex items-center cursor-pointer" @click="$emit('open')">
				<h5 class="flex-grow">{{ thread.name }}</h5>
				<div class="text-lime-800">{{ thread.messages.length }} messages</div>
			</div>
			<div v-if="thread.summary" class="mt-2">{{ thread.summary }}</div>
		</QCardSection>
	</QCard>
</template>

<script setup lang="ts">
defineEmits(["open"]);
defineProps<{
	thread: AgentThread;
}>();
</script>
