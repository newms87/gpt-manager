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

						<!-- Expandable Sections -->
						<div class="mt-3 space-y-2">
							<!-- Processes -->
							<div v-if="run.process_count > 0">
								<ShowHideButton
									v-model="expandedSections[`processes-${run.id}`]"
									:label="`Processes (${run.process_count})`"
									class="text-xs"
									color="purple"
									size="xs"
									@show="loadRunProcesses(run)"
								/>
								<div v-if="expandedSections[`processes-${run.id}`]" class="mt-2 bg-slate-800 rounded p-2 space-y-2">
									<QSkeleton v-if="loadingProcesses[run.id]" class="h-16" />
									<template v-else-if="run.processes">
										<div v-for="process in run.processes" :key="process.id" class="text-sm text-slate-300">
											<div class="flex items-center gap-2">
												<LabelPillWidget :label="`pid: ${process.id}`" color="sky" size="xs" />
												<LabelPillWidget v-if="process.operation" :label="process.operation" :color="getOperationColor(process.operation)" size="xs" />
												<span class="truncate">{{ process.activity }}</span>
												<WorkflowStatusTimerPill :runner="process" />
											</div>
										</div>
									</template>
								</div>
							</div>

							<!-- Input Artifacts -->
							<div v-if="run.input_artifacts_count > 0">
								<ShowHideButton
									v-model="expandedSections[`input-artifacts-${run.id}`]"
									:label="`Input Artifacts (${run.input_artifacts_count})`"
									class="text-xs"
									color="sky"
									size="xs"
									@show="loadRunInputArtifacts(run)"
								/>
								<div v-if="expandedSections[`input-artifacts-${run.id}`]" class="mt-2 bg-slate-800 rounded p-2">
									<QSkeleton v-if="loadingInputArtifacts[run.id]" class="h-16" />
									<ArtifactList
										v-else-if="run.inputArtifacts"
										dense
										:filter="{taskRun: {category: 'input', artifactable_id: run.id}}"
									/>
								</div>
							</div>

							<!-- Output Artifacts -->
							<div v-if="run.output_artifacts_count > 0">
								<ShowHideButton
									v-model="expandedSections[`output-artifacts-${run.id}`]"
									:label="`Output Artifacts (${run.output_artifacts_count})`"
									class="text-xs"
									color="green"
									size="xs"
									@show="loadRunOutputArtifacts(run)"
								/>
								<div v-if="expandedSections[`output-artifacts-${run.id}`]" class="mt-2 bg-slate-800 rounded p-2">
									<QSkeleton v-if="loadingOutputArtifacts[run.id]" class="h-16" />
									<ArtifactList
										v-else-if="run.outputArtifacts"
										dense
										:filter="{taskRun: {category: 'output', artifactable_id: run.id}}"
									/>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</InfoDialog>
</template>

<script setup lang="ts">
import { apiUrls } from "@/api";
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { useHashedColor } from "@/composables/useHashedColor";
import { TaskRun } from "@/types";
import { FaSolidClockRotateLeft } from "danx-icon";
import { QSkeleton, QSpinnerGears } from "quasar";
import { fDateTime, InfoDialog, LabelPillWidget, request, ShowHideButton } from "quasar-ui-danx";
import { onMounted, reactive, ref } from "vue";

const props = defineProps<{
	taskRun: TaskRun;
	isShowing?: boolean;
}>();

defineEmits<{
	close: [];
}>();

const isLoading = ref(false);
const historicalRuns = ref<TaskRun[]>([]);
const expandedSections = reactive<Record<string, boolean>>({});
const loadingProcesses = reactive<Record<number, boolean>>({});
const loadingInputArtifacts = reactive<Record<number, boolean>>({});
const loadingOutputArtifacts = reactive<Record<number, boolean>>({});

function getOperationColor(operation: string | undefined) {
	return useHashedColor(ref(operation)).value;
}

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

async function loadRunProcesses(run: TaskRun) {
	if (run.processes || loadingProcesses[run.id]) return;

	loadingProcesses[run.id] = true;
	try {
		const result = await dxTaskRun.routes.details(run, { processes: true }, { params: { withTrashed: true } });
		// Update the local run object since historicalRuns is not managed by the danx store
		if (result?.processes) {
			run.processes = result.processes;
		}
	} finally {
		loadingProcesses[run.id] = false;
	}
}

async function loadRunInputArtifacts(run: TaskRun) {
	if (run.inputArtifacts || loadingInputArtifacts[run.id]) return;

	loadingInputArtifacts[run.id] = true;
	try {
		const result = await dxTaskRun.routes.details(run, { inputArtifacts: true }, { params: { withTrashed: true } });
		// Update the local run object since historicalRuns is not managed by the danx store
		if (result?.inputArtifacts) {
			run.inputArtifacts = result.inputArtifacts;
		}
	} finally {
		loadingInputArtifacts[run.id] = false;
	}
}

async function loadRunOutputArtifacts(run: TaskRun) {
	if (run.outputArtifacts || loadingOutputArtifacts[run.id]) return;

	loadingOutputArtifacts[run.id] = true;
	try {
		const result = await dxTaskRun.routes.details(run, { outputArtifacts: true }, { params: { withTrashed: true } });
		// Update the local run object since historicalRuns is not managed by the danx store
		if (result?.outputArtifacts) {
			run.outputArtifacts = result.outputArtifacts;
		}
	} finally {
		loadingOutputArtifacts[run.id] = false;
	}
}

onMounted(() => {
	if (props.isShowing) {
		loadHistory();
	}
});
</script>
