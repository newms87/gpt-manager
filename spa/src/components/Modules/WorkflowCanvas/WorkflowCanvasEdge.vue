<template>
	<!-- You can use the `BaseEdge` component to create your own custom edge more easily -->
	<BaseEdge
		:id="edge.id"
		ref="edgeRef"
		:path="path[0]"
		:marker-end="edge.markerEnd as string"
		:style="edgeStyle"
		class="base-edge-dan"
		@mouseenter="isHoveringMenu = true"
	/>

	<!-- Use the `EdgeLabelRenderer` to escape the SVG world of edges and render your own custom label in a `<div>` ctx -->
	<EdgeLabelRenderer>
		<div
			ref="labelRef"
			:style="{
        visibility: isAnimating ? 'visible' : 'hidden',
        position: 'absolute',
        zIndex: 1,
        offsetPath: `path('${path[0]}')`,
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";

defineEmits<{
	(e: "remove", edge: EdgeProps): void;
}>();

const props = defineProps<{
	edge: EdgeProps,
	nodes: Node[],
}>();

const sourceNode = computed(() => props.nodes.find((node: Node) => node.id === props.edge.source));
const targetNode = computed(() => props.nodes.find((node) => node.id === props.edge.target));
const labelRef = ref();
const edgeRef = ref();

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

let animationInterval = null;
const isAnimating = ref(false);

onMounted(() => {
	animationInterval = setInterval(runAnimation, 3000);
});

onUnmounted(() => {
	if (animationInterval) {
		clearInterval(animationInterval);
	}
});

function runAnimation() {
	const pathEl: SVGGeometryElement = edgeRef.value?.pathEl as SVGGeometryElement;
	const labelEl = labelRef.value;

	if (!pathEl || !labelEl) {
		console.warn("Path or label element not found");
		return;
	}

	const totalLength = pathEl.getTotalLength();

	isAnimating.value = true;

	// We need to wait for the next tick to ensure that the label element is rendered
	nextTick(() => {
		const keyframes = [{ offsetDistance: "0%" }, { offsetDistance: "100%" }];

		// use path length as a possible measure for the animation duration
		const pathLengthDuration = totalLength * 10;

		const labelAnimation = labelEl.animate(keyframes, {
			duration: Math.min(Math.max(pathLengthDuration, 1500), 2000), // clamp duration between 1.5s and 3s
			direction: "normal",
			easing: "ease-in-out",
			iterations: 1
		});

		const handleAnimationEnd = () => {
			isAnimating.value = false;
		};

		labelAnimation.onfinish = handleAnimationEnd;
		labelAnimation.oncancel = handleAnimationEnd;
	});
}
</script>

<style lang="scss" scoped>
.artifact-transit-icon {
	font-size: 28px;
	transform: scaleX(-1);
}
</style>
