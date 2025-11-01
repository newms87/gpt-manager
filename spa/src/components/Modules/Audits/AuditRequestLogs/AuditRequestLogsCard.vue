<template>
	<div class="audit-request-logs-card bg-gray-950 text-slate-400 rounded overflow-hidden">
		<LogViewerContainer v-if="logs" :logs="logs" />
		<div v-else class="p-4 text-center text-slate-500">
			(No logs available)
		</div>
	</div>
</template>

<script setup lang="ts">
import LogViewerContainer from './LogViewerContainer.vue';

defineProps<{
	logs?: string | null;
}>();
</script>
