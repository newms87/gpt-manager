<template>
	<g>
		<path
			class="vue-flow__connection animated"
			fill="none"
			stroke="#14532D"
			:stroke-width="3"
			:d="`M${sourceX},${sourceY} C ${sourceX} ${targetY} ${sourceX} ${targetY} ${targetX},${targetY}`"
		/>

		<circle :cx="targetX" :cy="targetY" fill="#fff" :r="4" stroke="#86EFAC" :stroke-width="1.5" />
	</g>
</template>

<script setup lang="ts">
defineProps<{
	sourceX: number;
	sourceY: number;
	targetX: number;
	targetY: number;
}>();
</script>
