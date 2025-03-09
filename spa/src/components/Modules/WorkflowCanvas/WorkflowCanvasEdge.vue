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
			v-if="transitionPercent > 0 && tweenTransitionPosition < 100"
			ref="labelRef"
			class="z-[1000]"
			:style="{
				position: 'absolute',
				zIndex: 1,
				offsetPath: `path('${path[0]}')`,
				offsetDistance: `${tweenTransitionPosition}%`,
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
import { TaskWorkflowRun } from "@/types";
import { BaseEdge, EdgeLabelRenderer, EdgeProps, getBezierPath, Node, useVueFlow } from "@vue-flow/core";
import { TransitionPresets, useTransition, UseTransitionOptions } from "@vueuse/core";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

defineEmits<{ (e: "remove", edge: EdgeProps): void }>();

const props = defineProps<{ taskWorkflowRun?: TaskWorkflowRun, edge: EdgeProps; nodes: Node[]; }>();

const sourceTaskRun = computed(() => props.taskWorkflowRun?.taskRuns?.find((tr) => tr.task_workflow_node_id == +props.edge.source));
const targetTaskRun = computed(() => props.taskWorkflowRun?.taskRuns?.find((tr) => tr.task_workflow_node_id == +props.edge.target));
const transitionPercent = ref(0);
const tweenTransitionPosition = useTransition(transitionPercent, {
	duration: 2000,
	easing: TransitionPresets.easeInOutCubic
} as UseTransitionOptions);
watch(() => props.taskWorkflowRun, () => {
	if (["Completed", "Running"].includes(targetTaskRun.value?.status)) {
		transitionPercent.value = 100;
	} else if (targetTaskRun.value?.status === "Pending") {
		transitionPercent.value = 75;
	} else if (sourceTaskRun.value?.status === "Completed") {
		transitionPercent.value = 25;
	} else {
		transitionPercent.value = 0;
	}
}, { deep: true });

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

onEdgeMouseEnter(({ edge }) => +edge.id == +props.edge.id && (isHoveringLine.value = true));
onEdgeMouseLeave(({ edge }) => +edge.id == +props.edge.id && (isHoveringLine.value = false));

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
