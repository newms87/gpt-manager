<template>
	<div class="flex items-stretch">
		<div
			class="rounded-l-lg bg-slate-400 text-slate-900 text-xs px-2 flex items-center justify-center"
			:class="labelClass"
		>
			<slot name="label">{{ label }}</slot>
		</div>
		<div class="rounded-r-lg bg-slate-900 text-slate-400 px-2 py-1">
			<slot name="value">{{ value }}</slot>
		</div>
	</div>
</template>
<script setup lang="ts">
withDefaults(defineProps<{
	label: string;
	value: string;
	labelClass?: string;
}>(), {
	labelClass: ""
});
</script>
