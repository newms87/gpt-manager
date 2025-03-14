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
			v-if="transitionPercent > 0 && transitionPercent < 100"
			ref="labelRef"
			class="z-[1000]"
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
				<DeliveryBoyLottie :autoplay="isTransitioning" class="w-[7rem]" />
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
				size="sm"
				@click="$emit('remove', edge)"
			/>
		</div>
	</EdgeLabelRenderer>
</template>

<script setup lang="ts">
import { DeliveryBoyLottie } from "@/assets/dotlottie";
import { TaskWorkflowRun } from "@/types";
import {
	BaseEdge,
	EdgeLabelRenderer,
	EdgeProps,
	getBezierPath,
	getSmoothStepPath,
	Node,
	useVueFlow
} from "@vue-flow/core";
import { executeTransition } from "@vueuse/core";
import { ActionButton, waitForRef } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

defineEmits<{ (e: "remove", edge: EdgeProps): void }>();

const props = withDefaults(defineProps<{
	pathType?: "bezier" | "smoothstep";
	taskWorkflowRun?: TaskWorkflowRun,
	edge: EdgeProps;
	nodes: Node[];
}>(), {
	pathType: "smoothstep",
	taskWorkflowRun: null
});

const sourceTaskRun = computed(() => props.taskWorkflowRun?.taskRuns?.find((tr) => tr.task_workflow_node_id == +props.edge.source));
const targetTaskRun = computed(() => props.taskWorkflowRun?.taskRuns?.find((tr) => tr.task_workflow_node_id == +props.edge.target));
const transitionPercent = ref(0);
const isTransitioning = ref(false);
watch(() => props.taskWorkflowRun, async () => {
	let newPercent = 0;
	if (["Completed", "Running"].includes(targetTaskRun.value?.status)) {
		newPercent = 100;
	} else if (targetTaskRun.value?.status === "Pending") {
		newPercent = 75;
	} else if (sourceTaskRun.value?.status === "Completed") {
		newPercent = 25;
	}

	if (newPercent === transitionPercent.value) return;

	// If the new percent is less than the current percent, reset the transition as this is a different task run
	if (newPercent < transitionPercent.value) {
		transitionPercent.value = 0;
	}


	let duration = 0;

	// If the workflow is completed, we want to leave duration at 0 (no animations)
	if (props.taskWorkflowRun.status !== "Completed") {
		// Move the truck at a constant velocity. The duration is calculated based on the distance to travel.
		duration = (newPercent - transitionPercent.value) / 100 * 3000;
	}

	// noinspection TypeScriptValidateTypes IDE doesn't recognize the Ref imported
	await waitForRef(isTransitioning, false);

	isTransitioning.value = true;
	await executeTransition(transitionPercent, transitionPercent.value, newPercent, { duration });
	isTransitioning.value = false;
}, { deep: true });

const path = ref(computePath());
watch(() => props.edge, () => path.value = computePath(), { deep: true });

function computePath() {
	const params = {
		sourceX: props.edge.sourceX,
		sourceY: props.edge.sourceY,
		sourcePosition: props.edge.sourcePosition,
		targetX: props.edge.targetX,
		targetY: props.edge.targetY,
		targetPosition: props.edge.targetPosition
	};

	if (props.pathType === "smoothstep") {
		return getSmoothStepPath(params);
	}
	return getBezierPath(params);
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
