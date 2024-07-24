<template>
	<div class="bg-gray-950 text-slate-400 rounded p-2">
		<div v-if="auditRequestId" class="mb-3">
			<a
				target="_blank"
				:href="$router.resolve({path: `/audit-requests/${auditRequestId}/errors`}).href"
			>
				View Errors
			</a>
		</div>
		<template v-if="logs">
			<div v-for="(log, index) in logs.split('\n') || ['(Logs Empty)']" :key="index">{{ log }}</div>
		</template>
		<div v-else>
			(No logs available)
		</div>
	</div>
</template>
<script setup lang="ts">
defineProps<{
	auditRequestId?: string | number | null;
	logs?: string | null;
}>();
</script>
