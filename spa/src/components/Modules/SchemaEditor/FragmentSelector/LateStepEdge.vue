<template>
	<BaseEdge :path="path" :style="style" :marker-end="markerEnd" />
</template>

<script setup lang="ts">
import { BaseEdge, EdgeProps, Position } from "@vue-flow/core";
import { computed } from "vue";

const props = defineProps<EdgeProps>();

/** Distance from target where the step/bend occurs */
const STEP_DISTANCE = 50;

/** Radius for rounded corners */
const CORNER_RADIUS = 12;

/**
 * Compute a custom edge path that steps late (close to target) rather than in the middle.
 * Uses quadratic bezier curves for rounded corners.
 */
const path = computed(() => {
	const { sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition } = props;

	// Handle horizontal flow (source on right, target on left)
	if (sourcePosition === Position.Right && targetPosition === Position.Left) {
		// If nearly same Y, just draw a straight line
		if (Math.abs(targetY - sourceY) < 5) {
			return `M ${sourceX} ${sourceY} L ${targetX} ${targetY}`;
		}

		// Calculate the X position where we start the vertical step
		const stepX = targetX - STEP_DISTANCE;
		const midX = (sourceX + targetX) / 2;
		const actualStepX = Math.max(stepX, midX);

		// Determine if we're going up or down
		const goingDown = targetY > sourceY;
		const r = Math.min(CORNER_RADIUS, Math.abs(targetY - sourceY) / 2, (actualStepX - sourceX) / 2);

		// Path with rounded corners using quadratic bezier curves
		// First corner: at (actualStepX, sourceY)
		// Second corner: at (actualStepX, targetY)
		if (goingDown) {
			return `M ${sourceX} ${sourceY}
				L ${actualStepX - r} ${sourceY}
				Q ${actualStepX} ${sourceY} ${actualStepX} ${sourceY + r}
				L ${actualStepX} ${targetY - r}
				Q ${actualStepX} ${targetY} ${actualStepX + r} ${targetY}
				L ${targetX} ${targetY}`;
		} else {
			return `M ${sourceX} ${sourceY}
				L ${actualStepX - r} ${sourceY}
				Q ${actualStepX} ${sourceY} ${actualStepX} ${sourceY - r}
				L ${actualStepX} ${targetY + r}
				Q ${actualStepX} ${targetY} ${actualStepX + r} ${targetY}
				L ${targetX} ${targetY}`;
		}
	}

	// Handle vertical flow (source on bottom, target on top)
	if (sourcePosition === Position.Bottom && targetPosition === Position.Top) {
		// If nearly same X, just draw a straight line
		if (Math.abs(targetX - sourceX) < 5) {
			return `M ${sourceX} ${sourceY} L ${targetX} ${targetY}`;
		}

		// Calculate the Y position where we start the horizontal step
		const stepY = targetY - STEP_DISTANCE;
		const midY = (sourceY + targetY) / 2;
		const actualStepY = Math.max(stepY, midY);

		// Determine if we're going left or right
		const goingRight = targetX > sourceX;
		const r = Math.min(CORNER_RADIUS, Math.abs(targetX - sourceX) / 2, (actualStepY - sourceY) / 2);

		// Path with rounded corners
		if (goingRight) {
			return `M ${sourceX} ${sourceY}
				L ${sourceX} ${actualStepY - r}
				Q ${sourceX} ${actualStepY} ${sourceX + r} ${actualStepY}
				L ${targetX - r} ${actualStepY}
				Q ${targetX} ${actualStepY} ${targetX} ${actualStepY + r}
				L ${targetX} ${targetY}`;
		} else {
			return `M ${sourceX} ${sourceY}
				L ${sourceX} ${actualStepY - r}
				Q ${sourceX} ${actualStepY} ${sourceX - r} ${actualStepY}
				L ${targetX + r} ${actualStepY}
				Q ${targetX} ${actualStepY} ${targetX} ${actualStepY + r}
				L ${targetX} ${targetY}`;
		}
	}

	// Fallback: straight line
	return `M ${sourceX} ${sourceY} L ${targetX} ${targetY}`;
});
</script>
