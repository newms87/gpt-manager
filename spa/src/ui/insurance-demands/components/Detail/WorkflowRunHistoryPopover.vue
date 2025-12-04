<template>
	<div class="workflow-run-history-popover relative">
		<!-- Trigger Button -->
		<button
			class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
			:class="workflowColors.buttonColor === 'sky'
				? 'bg-sky-600 hover:bg-sky-500 text-white'
				: 'bg-slate-700 hover:bg-slate-600 text-slate-200'"
		>
			<FaSolidClockRotateLeft class="w-4 h-4" />
			<span class="text-sm font-medium">History</span>
			<span
				class="bg-white/20 text-xs font-bold px-1.5 py-0.5 rounded"
			>
				{{ workflowRuns.length }}
			</span>
		</button>

		<!-- Popover Content -->
		<QPopupProxy
			:offset="[0, 8]"
			transition-show="scale"
			transition-hide="scale"
			class="workflow-run-history-popup"
		>
			<div class="bg-slate-800 rounded-lg shadow-xl border border-slate-700 w-96 max-h-[600px] flex flex-col">
				<!-- Header -->
				<div class="px-4 py-3 border-b border-slate-700">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-slate-200 font-semibold text-base">
							Run History
						</h3>
						<LabelPillWidget
							:label="`${workflowRuns.length} run${workflowRuns.length !== 1 ? 's' : ''}`"
							:color="workflowColor || 'slate'"
							size="xs"
						/>
					</div>
					<p class="text-slate-400 text-xs">
						Select a run to view its details
					</p>
				</div>

				<!-- Scrollable List -->
				<div class="overflow-y-auto flex-grow">
					<WorkflowRunHistoryItem
						v-for="run in sortedRuns"
						:key="run.id"
						:run="run"
						:is-latest="run.id === currentRun.id"
						:is-selected="run.id === currentRun.id"
						:workflow-color="workflowColor"
						@select="onSelectRun"
					/>
				</div>
			</div>
		</QPopupProxy>
	</div>
</template>

<script setup lang="ts">
import type { WorkflowRun } from "@/types";
import { getWorkflowColors } from "@/ui/insurance-demands/config";
import { FaSolidClockRotateLeft } from "danx-icon";
import { LabelPillWidget } from "quasar-ui-danx";
import { QPopupProxy } from "quasar";
import { computed } from "vue";
import WorkflowRunHistoryItem from "./WorkflowRunHistoryItem.vue";

const props = defineProps<{
	workflowRuns: WorkflowRun[];
	currentRun: WorkflowRun;
	workflowColor?: string;
}>();

const emit = defineEmits<{
	"select-run": [run: WorkflowRun];
}>();

// Workflow colors
const workflowColors = computed(() => getWorkflowColors(props.workflowColor || "slate"));

// Sort runs by created_at descending (newest first)
const sortedRuns = computed(() => {
	return [...props.workflowRuns].sort((a, b) => {
		const dateA = new Date(a.created_at).getTime();
		const dateB = new Date(b.created_at).getTime();
		return dateB - dateA;
	});
});

// Handle run selection
function onSelectRun(run: WorkflowRun) {
	emit("select-run", run);
}
</script>

<style lang="scss">
// Global styles for the popup (not scoped because QPopupProxy renders outside component)
.workflow-run-history-popup {
	.q-menu {
		background: transparent !important;
		box-shadow: none !important;
	}
}
</style>
