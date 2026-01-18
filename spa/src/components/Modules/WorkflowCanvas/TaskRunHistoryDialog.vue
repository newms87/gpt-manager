<template>
	<InfoDialog
		v-if="isShowing"
		:title="`Task Run Restart History - rid: ${taskRun.id}`"
		content-class="w-[85vw] h-[80vh] bg-slate-950"
		@close="$emit('close')"
	>
		<div class="h-full flex flex-col overflow-hidden">
			<!-- Current Run Info -->
			<div class="bg-slate-800 p-4 rounded-lg mb-4">
				<div class="flex items-center space-x-2 mb-2">
					<LabelPillWidget label="Current" color="green" size="sm" />
					<span class="text-slate-300 font-medium">{{ taskRun.step }}</span>
				</div>
				<div class="flex items-center space-x-3 text-sm">
					<LabelPillWidget :label="`rid: ${taskRun.id}`" color="sky" size="xs" />
					<WorkflowStatusTimerPill :runner="taskRun" />
					<span class="text-slate-400">Restarts: {{ taskRun.restart_count || 0 }}</span>
					<span class="text-slate-400">Processes: {{ taskRun.process_count || 0 }}</span>
				</div>
			</div>

			<!-- History Section -->
			<div class="flex-grow overflow-y-auto">
				<div v-if="isLoading" class="flex items-center justify-center py-12">
					<QSpinnerGears class="text-orange-500 w-10 h-10" />
					<p class="ml-3 text-slate-400">Loading history...</p>
				</div>

				<div v-else-if="historicalRuns.length === 0" class="text-center py-12">
					<FaSolidClockRotateLeft class="w-12 h-12 text-slate-600 mx-auto mb-3" />
					<p class="text-slate-500">No previous runs found</p>
				</div>

				<div v-else class="space-y-4">
					<div class="text-sm text-slate-400 mb-2">
						Previous Runs ({{ historicalRuns.length }})
					</div>
					<div
						v-for="(run, index) in historicalRuns"
						:key="run.id"
						class="bg-slate-900 rounded-lg p-4 border border-slate-700"
					>
						<!-- Run Header -->
						<div class="flex items-center justify-between mb-3">
							<div class="flex items-center space-x-2">
								<LabelPillWidget
									:label="`Attempt ${historicalRuns.length - index}`"
									color="orange"
									size="sm"
								/>
								<LabelPillWidget :label="`rid: ${run.id}`" color="sky" size="xs" />
							</div>
							<WorkflowStatusTimerPill :runner="run" />
						</div>

						<!-- Run Step -->
						<div class="text-slate-300 text-sm mb-3">
							{{ run.step }}
						</div>

						<!-- Run Details -->
						<div class="flex items-center flex-wrap gap-2 text-xs">
							<LabelPillWidget
								:label="`${run.process_count || 0} process${run.process_count !== 1 ? 'es' : ''}`"
								color="purple"
								size="xs"
							/>
							<LabelPillWidget
								:label="`${run.input_artifacts_count || 0} input artifact${run.input_artifacts_count !== 1 ? 's' : ''}`"
								color="sky"
								size="xs"
							/>
							<LabelPillWidget
								:label="`${run.output_artifacts_count || 0} output artifact${run.output_artifacts_count !== 1 ? 's' : ''}`"
								color="green"
								size="xs"
							/>
							<span class="text-slate-500">
								Created: {{ fDateTime(run.created_at) }}
							</span>
							<span v-if="run.started_at" class="text-slate-500">
								| Started: {{ fDateTime(run.started_at) }}
							</span>
							<span v-if="run.completed_at || run.failed_at" class="text-slate-500">
								| Ended: {{ fDateTime(run.completed_at || run.failed_at) }}
							</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</InfoDialog>
</template>

<script setup lang="ts">
import { apiUrls } from "@/api";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { TaskRun } from "@/types";
import { FaSolidClockRotateLeft } from "danx-icon";
import { QSpinnerGears } from "quasar";
import { fDateTime, InfoDialog, LabelPillWidget, request } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

const props = defineProps<{
	taskRun: TaskRun;
	isShowing?: boolean;
}>();

defineEmits<{
	close: [];
}>();

const isLoading = ref(false);
const historicalRuns = ref<TaskRun[]>([]);

async function loadHistory() {
	isLoading.value = true;
	try {
		const response = await request.get(apiUrls.tasks.runHistory({ id: props.taskRun.id }));
		historicalRuns.value = response.data || [];
	} catch (error) {
		console.error("Failed to load task run history:", error);
		historicalRuns.value = [];
	} finally {
		isLoading.value = false;
	}
}

onMounted(() => {
	if (props.isShowing) {
		loadHistory();
	}
});
</script>
