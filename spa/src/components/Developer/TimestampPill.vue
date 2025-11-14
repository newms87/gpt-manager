<template>
	<div class="inline-flex items-center rounded-full overflow-hidden text-xs font-medium">
		<!-- Created Section -->
		<div class="px-2.5 py-1 bg-slate-700 text-slate-300">
			<span class="font-semibold">created:</span>
			<span class="ml-1 font-mono">{{ formattedCreatedTime }}</span>
			<span class="ml-1 text-slate-500">({{ formattedElapsedTime }} ago)</span>
		</div>

		<!-- Divider -->
		<div class="w-px h-4 bg-slate-600" />

		<!-- Expires Section -->
		<div
			class="px-2.5 py-1"
			:class="expiresBackgroundClass"
		>
			<span class="font-semibold" :class="expiresTextClass">expires in</span>
			<span class="ml-1 font-mono" :class="expiresTextClass">{{ formattedTimeRemaining }}</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
	createdAt: Date;
	expiresAt: string;
	currentTime: number;
}>();

const formattedCreatedTime = computed(() => {
	const hours = props.createdAt.getHours().toString().padStart(2, '0');
	const minutes = props.createdAt.getMinutes().toString().padStart(2, '0');
	const seconds = props.createdAt.getSeconds().toString().padStart(2, '0');
	return `${hours}:${minutes}:${seconds}`;
});

const formattedElapsedTime = computed(() => {
	const elapsed = props.currentTime - props.createdAt.getTime();
	const seconds = Math.floor(elapsed / 1000);

	if (seconds < 60) return `${seconds}s`;

	const minutes = Math.floor(seconds / 60);
	if (minutes < 60) {
		const remainingSeconds = seconds % 60;
		return `${minutes}m ${remainingSeconds}s`;
	}

	const hours = Math.floor(minutes / 60);
	const remainingMinutes = minutes % 60;
	return `${hours}h ${remainingMinutes}m`;
});

const timeRemaining = computed(() => {
	const expiresAt = new Date(props.expiresAt);
	return expiresAt.getTime() - props.currentTime;
});

const isExpiringSoon = computed(() => {
	return timeRemaining.value > 0 && timeRemaining.value < 60000; // Less than 1 minute
});

const formattedTimeRemaining = computed(() => {
	const ms = timeRemaining.value;
	if (ms < 0) return "EXPIRED";
	const seconds = Math.floor(ms / 1000);
	if (seconds < 60) return `${seconds}s`;
	const minutes = Math.floor(seconds / 60);
	const remainingSeconds = seconds % 60;
	return `${minutes}m ${remainingSeconds}s`;
});

const expiresBackgroundClass = computed(() => {
	if (isExpiringSoon.value) return "bg-orange-600";
	return "bg-slate-950";
});

const expiresTextClass = computed(() => {
	if (isExpiringSoon.value) return "text-orange-100";
	return "text-slate-300";
});
</script>
