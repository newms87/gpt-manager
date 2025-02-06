<template>
	<div class="bg-gray-950 text-slate-400 rounded p-2">
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
	logs?: string | null;
}>();
</script>
