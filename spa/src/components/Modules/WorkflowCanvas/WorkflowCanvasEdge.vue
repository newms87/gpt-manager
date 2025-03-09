<template>
	<BaseEdge
		:id="edge.id"
		ref="edgeRef"
		:path="path[0]"
		:marker-end="edge.markerEnd as string"
		:style="edgeStyle"
		class="base-edge-dan"
	/>

	<EdgeLabelRenderer>
		<div
			v-if="transitionPercent > 0"
			ref="labelRef"
			:style="{
				position: 'absolute',
				zIndex: 1,
				offsetPath: `path('${path[0]}')`,
				offsetDistance: `${transitionPercent}%`,
				offsetRotate: '0deg',
				offsetAnchor: 'center',
			}"
		>
			<div class="artifact-transit-icon relative inline-block">
				🚚
			</div>
		</div>

		<div
			:style="{
				pointerEvents: 'all',
				position: 'absolute',
				transform: `translate(-50%, -50%) translate(${path[1]}px,${path[2]}px)`,
			}"
			class="nodrag nopan group z-[1000]"
			@mouseenter="isHoveringMenu = true"
			@mouseleave="isHoveringMenu = false"
		>
			<ActionButton
				type="trash"
				color="red"
				class="transition-all"
				:class="{'opacity-100': isHovering, 'opacity-0': !isHovering}"
				@click="$emit('remove', edge)"
			/>
		</div>
	</EdgeLabelRenderer>
</template>

<script setup lang="ts">
import { BaseEdge, EdgeLabelRenderer, EdgeProps, getBezierPath, Node, useVueFlow } from "@vue-flow/core";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

defineEmits<{ (e: "remove", edge: EdgeProps): void }>();

const props = defineProps<{ edge: EdgeProps; nodes: Node[]; }>();

const targetNode = computed(() => props.nodes.find((node) => node.id === props.edge.target));
const transitionPercent = ref(0);
watch(() => targetNode.value, () => {
	console.log("targetNode", targetNode.value);
});

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

onEdgeMouseEnter(({ edge }) => +edge.id == edge.id && (isHoveringLine.value = true));
onEdgeMouseLeave(({ edge }) => +edge.id == edge.id && (isHoveringLine.value = false));

const edgeStyle = computed(() => {
	return {
		stroke: isHovering.value ? "#86EFAC" : "#14532D",
		strokeWidth: isHovering.value ? 4 : 3
	};
});
</script>

<style lang="scss" scoped>
.artifact-transit-icon {
	font-size: 28px;
	transform: scaleX(-1);
}
</style>
