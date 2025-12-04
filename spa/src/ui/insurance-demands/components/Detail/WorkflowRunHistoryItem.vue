<template>
	<div
		class="workflow-run-history-item flex items-center gap-4 px-4 py-3 cursor-pointer transition-all"
		:class="[
			isSelected ? 'bg-slate-700 border-l-4' : 'bg-slate-800 hover:bg-slate-750 border-l-4 border-transparent',
			isSelected ? workflowColors.palette.borderPrimary : ''
		]"
		@click="$emit('select', props.run)"
	>
		<!-- Status Icon (Left) -->
		<div
			class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg"
			:class="statusIconClasses"
		>
			<component :is="statusIcon" class="w-5 h-5" />
		</div>

		<!-- Run Info (Center) -->
		<div class="flex-grow min-w-0">
			<!-- Line 1: Run ID + Current Badge -->
			<div class="flex items-center gap-2 mb-1">
				<span class="font-semibold text-slate-200 text-sm">
					Run #{{ run.id }}
				</span>
				<LabelPillWidget
					v-if="isLatest"
					label="Current"
					:color="workflowColor || 'sky'"
					size="xs"
				/>
			</div>

			<!-- Line 2: Date/Time -->
			<div class="text-slate-400 text-xs mb-1">
				{{ fDateTime(run.created_at) }}
			</div>

			<!-- Line 3: Runtime Info -->
			<div class="text-slate-300 text-xs mb-2">
				{{ runtimeText }}
			</div>

			<!-- Line 4: Progress Bar (if running) -->
			<QLinearProgress
				v-if="isRunning"
				:value="run.progress_percent || 0"
				:color="workflowColor || 'sky'"
				class="rounded-full"
				size="4px"
			/>
		</div>

		<!-- Status Text (Right) -->
		<div class="flex-shrink-0 text-xs font-medium" :class="statusTextClasses">
			{{ run.status }}
		</div>
	</div>
</template>

<script setup lang="ts">
import type { WorkflowRun } from "@/types";
import { getWorkflowColors } from "@/ui/insurance-demands/config";
import {
	isWorkflowActive,
	isWorkflowCompleted,
	isWorkflowFailed,
	isWorkflowStopped
} from "@/ui/insurance-demands/composables/useWorkflowState";
import {
	FaSolidCheck,
	FaSolidClock,
	FaSolidPause,
	FaSolidTriangleExclamation
} from "danx-icon";
import { fDateTime, fDuration, LabelPillWidget } from "quasar-ui-danx";
import { QLinearProgress } from "quasar";
import { computed } from "vue";

const props = defineProps<{
	run: WorkflowRun;
	isLatest: boolean;
	isSelected: boolean;
	workflowColor?: string;
}>();

defineEmits<{
	select: [run: WorkflowRun];
}>();

// Workflow colors
const workflowColors = computed(() => getWorkflowColors(props.workflowColor || "slate"));

// Status checks
const isRunning = computed(() => isWorkflowActive(props.run));
const isCompleted = computed(() => isWorkflowCompleted(props.run));
const isFailed = computed(() => isWorkflowFailed(props.run));
const isStopped = computed(() => isWorkflowStopped(props.run));

// Status icon
const statusIcon = computed(() => {
	if (isCompleted.value) return FaSolidCheck;
	if (isFailed.value) return FaSolidTriangleExclamation;
	if (isStopped.value) return FaSolidPause;
	return FaSolidClock;
});

// Status icon background classes
const statusIconClasses = computed(() => {
	if (isCompleted.value) return "bg-green-600 text-green-100";
	if (isFailed.value) return "bg-red-600 text-red-100";
	if (isStopped.value) return "bg-orange-600 text-orange-100";
	return "bg-sky-600 text-sky-100";
});

// Status text classes
const statusTextClasses = computed(() => {
	if (isCompleted.value) return "text-green-400";
	if (isFailed.value) return "text-red-400";
	if (isStopped.value) return "text-orange-400";
	return "text-sky-400";
});

// Runtime text
const runtimeText = computed(() => {
	const endTime = props.run.failed_at || props.run.completed_at || props.run.stopped_at;

	if (isRunning.value) {
		const duration = fDuration(props.run.started_at);
		return `Running for ${duration}`;
	}

	if (isCompleted.value) {
		const duration = fDuration(props.run.started_at, endTime);
		return `Completed in ${duration}`;
	}

	if (isFailed.value) {
		const duration = fDuration(props.run.started_at, endTime);
		return `Failed after ${duration}`;
	}

	if (isStopped.value) {
		const duration = fDuration(props.run.started_at, endTime);
		return `Stopped after ${duration}`;
	}

	return "Pending";
});
</script>

<style lang="scss" scoped>
.workflow-run-history-item {
	&:hover {
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
	}
}

// Intermediate bg color for hover
.bg-slate-750 {
	background-color: rgb(41, 50, 65);
}
</style>
