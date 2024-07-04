<template>
	<div class="bg-gray-950 text-slate-400 rounded p-2">
		<div class="mb-3">
			<a
				v-if="auditRequestId"
				target="_blank"
				:href="$router.resolve({path: `/audit-requests/${auditRequestId}/errors`}).href"
			>
				View Errors
			</a>
		</div>
		<div v-for="(log, index) in logs.split('\n') || ['(Logs Empty)']" :key="index">{{ log }}</div>
	</div>
</template>
<script setup lang="ts">
defineProps<{
	auditRequestId: string | number;
	logs: string;
}>();
</script>
