<template>
	<div class="workflow-status-progress-bar w-48">
		<div
			class="relative flex items-stretch flex-nowrap w-full h-6 rounded-xl overflow-hidden"
		>
			<div class="workflow-progress completed bg-green-800" :style="{width: completedPercent + '%'}"></div>
			<div class="workflow-progress running bg-sky-800" :style="{width: runningPercent + '%'}"></div>
			<div class="workflow-progress pending bg-slate-700" :style="{width: pendingPercent + '%'}"></div>
			<div class="workflow-progress failed bg-red-900" :style="{width: failedPercent + '%'}"></div>
			<div class="absolute-top-left w-full h-full flex justify-center items-center text-slate-300">
				<template v-if="statuses.total_count > 0">
					{{ statuses.total_count }} Runs: {{ completedPercent.toFixed(0) }}%
				</template>
				<template v-else>
					Waiting for runs...
				</template>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflowRun } from "@/components/Modules/Workflows/WorkflowRuns";
import { WorkflowInput, WorkflowRunStatuses } from "@/types";
import { autoRefreshObject, stopAutoRefreshObject } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps<{ workflowInput: WorkflowInput }>();

onMounted(() => {
	autoRefreshObject(
		props.workflowInput,
		() => statuses.value.total_count === undefined || props.workflowInput.has_active_workflow_run || (statuses.value?.pending_count + statuses.value?.running_count) > 0,
		loadWorkflowRunStatuses
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(props.workflowInput);
});

const statuses = ref<Partial<WorkflowRunStatuses>>({});
const pendingPercent = computed(() => !statuses.value.total_count ? 100 : (statuses.value.pending_count / (statuses.value.total_count || 1) * 100));
const runningPercent = computed(() => statuses.value.running_count / (statuses.value.total_count || 1) * 100);
const completedPercent = computed(() => statuses.value.completed_count / (statuses.value.total_count || 1) * 100);
const failedPercent = computed(() => statuses.value.failed_count / (statuses.value.total_count || 1) * 100);

async function loadWorkflowRunStatuses(wi: WorkflowInput) {
	statuses.value = await dxWorkflowRun.routes.runStatuses({ filter: { workflow_input_id: wi.id } });
	return wi;
}

</script>

<style lang="scss" scoped>
.workflow-progress {
	@apply h-full;
}
</style>
