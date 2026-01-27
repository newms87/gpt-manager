<template>
	<!-- Violet diamond indicating ACD type handle -->
	<div
		class="w-2.5 h-2.5 -ml-[.1rem] rotate-45 bg-violet-400"
		:class="direction === 'LR' ? '-mt-[0.15rem]' : '-mt-0.5'"
	/>
</template>

<script setup lang="ts">
defineProps<{
	direction: "LR" | "TB";
}>();
</script>
