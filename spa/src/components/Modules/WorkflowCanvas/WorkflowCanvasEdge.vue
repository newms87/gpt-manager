<template>
	<!-- You can use the `BaseEdge` component to create your own custom edge more easily -->
	<BaseEdge :id="edge.id" :path="path[0]" :marker-end="edge.markerEnd as string" :style="edgeStyle" />

	<!-- Use the `EdgeLabelRenderer` to escape the SVG world of edges and render your own custom label in a `<div>` ctx -->
	<EdgeLabelRenderer>
		<div
			:style="{
        pointerEvents: 'all',
        position: 'absolute',
        transform: `translate(-50%, -50%) translate(${path[1]}px,${path[2]}px)`,
      }"
			class="nodrag nopan group"
			@mouseenter="isHoveringMenu = true"
			@mouseleave="isHoveringMenu = false"
		>
			<ActionButton
				type="trash"
				color="red"
				class="transition-all"
				:class="{'opacity-100': isHovering, 'opacity-0': !isHovering}"
				@click="$emit('remove')"
			/>
		</div>
	</EdgeLabelRenderer>
</template>

<script setup lang="ts">
import { ActionButton } from "@/components/Shared";
import { BaseEdge, EdgeLabelRenderer, EdgeProps, getBezierPath, useVueFlow } from "@vue-flow/core";
import { computed, ref, watch } from "vue";

defineEmits<{
	(e: "remove"): void;
}>();

const props = defineProps<{
	edge: EdgeProps
}>();

const path = ref(computePath());
watch(() => props.edge, () => path.value = computePath(), { deep: true });
function computePath() {
	return getBezierPath({
		sourceX: props.edge.sourceX,
		sourceY: props.edge.sourceY,
		sourcePosition: props.edge.sourcePosition,
		targetX: props.edge.targetX,
		targetY: props.edge.targetY,
		targetPosition: props.edge.targetPosition
	});
}

const isHoveringMenu = ref(false);
const isHoveringLine = ref(false);
const isHovering = computed(() => isHoveringMenu.value || isHoveringLine.value);
const { onEdgeMouseEnter, onEdgeMouseLeave } = useVueFlow();
onEdgeMouseEnter(({ edge }) => +edge.id == props.edge.id && (isHoveringLine.value = true));
onEdgeMouseLeave(({ edge }) => +edge.id == props.edge.id && (isHoveringLine.value = false));

const edgeStyle = computed(() => {
	return {
		stroke: isHovering.value ? "#86EFAC" : "#14532D",
		strokeWidth: isHovering.value ? 4 : 3
	};
});
</script>
