<template>
	<InfoDialog
		v-if="isShowing"
		:title="`Workflow Workers - ${workflowRun?.name || workflowRun?.id}`"
		content-class="w-[85vw] h-[85vh] overflow-hidden bg-slate-950"
		@close="$emit('close')"
	>
		<div class="h-full flex flex-col">
			<!-- Header Section with improved styling -->
			<div class="bg-gradient-to-br from-slate-800 to-slate-900 p-6 rounded-xl shadow-xl flex-shrink-0">


				<!-- Worker Statistics Grid -->
				<div class="grid grid-cols-4 gap-4 mb-6">
					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<WorkersIcon class="w-5 text-sky-400" />
							<LabelPillWidget label="Active" color="sky" size="xs" />
						</div>
						<div class="text-2xl font-bold text-sky-300">{{ workflowRun?.active_workers_count || 0 }}</div>
						<div class="text-xs text-slate-400 mt-1">Currently processing</div>
					</div>

					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<ServerIcon class="w-5 text-green-400" />
							<LabelPillWidget label="Maximum" color="green" size="xs" />
						</div>
						<div class="text-2xl font-bold text-green-300">{{ workflowDefinition?.max_workers || 20 }}</div>
						<div class="text-xs text-slate-400 mt-1">Worker limit</div>
					</div>

					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<AvailableIcon class="w-5 text-blue-400" />
							<LabelPillWidget label="Available" :color="availableSlots > 0 ? 'blue' : 'red'" size="xs" />
						</div>
						<div class="text-2xl font-bold" :class="availableSlots > 0 ? 'text-blue-300' : 'text-red-300'">
							{{ availableSlots }}
						</div>
						<div class="text-xs text-slate-400 mt-1">Can be dispatched</div>
					</div>

					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<PercentIcon class="w-5 text-purple-400" />
							<LabelPillWidget label="Utilization" color="purple" size="xs" />
						</div>
						<div class="text-2xl font-bold text-purple-300">{{ utilizationPercent }}%</div>
						<div class="text-xs text-slate-400 mt-1">Worker capacity</div>
					</div>
				</div>

				<!-- Max Workers Configuration -->
				<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700 flex-x space-x-4">
					<div class="flex-x space-x-2">
						<SettingsIcon class="w-5 text-slate-400" />
						<span class="text-sm font-medium text-slate-300">Maximum Workers:</span>
					</div>
					<NumberField
						v-model="maxWorkersInput"
						placeholder="20"
						class="w-32"
						size="sm"
						@update:model-value="onMaxWorkersChange"
					/>
					<div v-if="isUpdatingMaxWorkers" class="flex-x space-x-2 text-green-400">
						<QSpinner size="sm" />
						<span class="text-xs">Updating...</span>
					</div>
				</div>
			</div>

			<!-- Job Dispatches Section -->
			<div class="flex-grow overflow-hidden flex flex-col mt-6">
				<!-- Section Header with Actions -->
				<div class="flex-x justify-between mb-4 px-6">
					<div class="flex-x space-x-3">
						<h3 class="text-xl font-semibold text-slate-100">Active Job Dispatches</h3>
						<LabelPillWidget
							:label="`Total: ${activeJobDispatches?.length || 0}`"
							color="slate"
							size="sm"
						/>
						<div v-if="jobDispatchStats" class="flex-x space-x-2">
							<LabelPillWidget
								v-if="jobDispatchStats.running"
								:label="`Running: ${jobDispatchStats.running}`"
								color="sky"
								size="xs"
							/>
							<LabelPillWidget
								v-if="jobDispatchStats.pending"
								:label="`Pending: ${jobDispatchStats.pending}`"
								color="slate"
								size="xs"
							/>
						</div>
					</div>

					<!-- Action Buttons -->
					<div class="flex-x space-x-3">
						<ActionButton
							:icon="DispatchIcon"
							:label="`Dispatch ${availableSlots} Worker${availableSlots !== 1 ? 's' : ''}`"
							color="blue"
							size="sm"
							:disabled="availableSlots <= 0"
							:loading="isDispatchingWorkers"
							@click="onDispatchWorkers"
						/>

						<ActionButton
							type="refresh"
							color="sky"
							size="sm"
							:loading="isLoadingJobDispatches"
							tooltip="Refresh active job dispatches"
							@click="loadActiveJobDispatches"
						/>
					</div>
				</div>

				<!-- Job Dispatches Content -->
				<div class="flex-grow overflow-y-auto px-6 pb-6">
					<div v-if="isLoadingJobDispatches" class="flex flex-col items-center justify-center py-16">
						<QSpinner size="lg" color="slate-400" />
						<div class="text-slate-400 mt-4">Loading active job dispatches...</div>
					</div>

					<div
						v-else-if="!activeJobDispatches || activeJobDispatches.length === 0"
						class="flex flex-col items-center justify-center py-16 bg-slate-900/30 rounded-xl border-2 border-dashed border-slate-800"
					>
						<NoWorkersIcon class="w-16 text-slate-600 mb-4" />
						<div class="text-lg text-slate-400 mb-2">No active workers found</div>
						<div class="text-sm text-slate-500">Dispatch workers to start processing tasks</div>
					</div>

					<div v-else class="space-y-3">
						<!-- Group by status if there are many jobs -->
						<div v-for="group in jobDispatchGroups" :key="group.status" class="mb-6">
							<div class="flex-x space-x-2 mb-3 sticky top-0 bg-slate-950 py-2 z-10">
								<LabelPillWidget
									:label="group.label"
									:class="group.pillClass"
									size="sm"
								/>
								<span class="text-sm text-slate-400">({{ group.jobs.length }})</span>
							</div>
							<div class="space-y-3">
								<JobDispatchCard
									v-for="jobDispatch in group.jobs"
									:key="jobDispatch.id"
									:job="jobDispatch"
									class="transform transition-all hover:scale-[1.01] hover:shadow-lg"
								/>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</InfoDialog>
</template>

<script setup lang="ts">
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import JobDispatchCard from "@/components/Modules/Audits/JobDispatches/JobDispatchCard";
import { JOB_DISPATCH_STATUS } from "@/components/Modules/Audits/JobDispatches/statuses";
import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import { usePusher } from "@/helpers/pusher";
import { useAssistantDebug } from "@/composables/useAssistantDebug";
import { WorkflowDefinition, WorkflowRun } from "@/types";
import {
	FaSolidBolt as DispatchIcon,
	FaSolidCircleCheck as AvailableIcon,
	FaSolidGear as SettingsIcon,
	FaSolidPercent as PercentIcon,
	FaSolidServer as ServerIcon,
	FaSolidUsers as WorkersIcon,
	FaSolidUsersSlash as NoWorkersIcon
} from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, InfoDialog, LabelPillWidget, NumberField, request, storeObject } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	workflowRun?: WorkflowRun;
	workflowDefinition?: WorkflowDefinition;
	isShowing?: boolean;
}>();

defineEmits<{
	close: [];
}>();

const { debugWebSocketSubscribe, debugError } = useAssistantDebug();

const activeJobDispatches = ref<JobDispatch[]>([]);
const isLoadingJobDispatches = ref(false);
const isDispatchingWorkers = ref(false);
const isUpdatingMaxWorkers = ref(false);
const maxWorkersInput = ref(props.workflowDefinition?.max_workers || 20);
const updateMaxWorkersTimeout = ref<ReturnType<typeof setTimeout> | null>(null);

const availableSlots = computed(() => {
	const maxWorkers = props.workflowDefinition?.max_workers || 20;
	const activeWorkers = props.workflowRun?.active_workers_count || 0;
	return Math.max(0, maxWorkers - activeWorkers);
});

const utilizationPercent = computed(() => {
	const maxWorkers = props.workflowDefinition?.max_workers || 20;
	const activeWorkers = props.workflowRun?.active_workers_count || 0;
	return maxWorkers > 0 ? Math.round((activeWorkers / maxWorkers) * 100) : 0;
});

const jobDispatchStats = computed(() => {
	if (!activeJobDispatches.value?.length) return null;

	const stats = {
		running: 0,
		pending: 0
	};

	activeJobDispatches.value.forEach(job => {
		if (job.status === "Running") stats.running++;
		else if (job.status === "Pending") stats.pending++;
	});

	return stats;
});

const jobDispatchGroups = computed(() => {
	if (!activeJobDispatches.value?.length) return [];

	const groups: Record<string, { label: string; pillClass: string; jobs: JobDispatch[] }> = {};

	activeJobDispatches.value.forEach(job => {
		const status = JOB_DISPATCH_STATUS.resolve(job.status);

		if (!groups[job.status]) {
			groups[job.status] = {
				label: status.value,
				pillClass: status.classPrimary,
				jobs: []
			};
		}

		groups[job.status].jobs.push(job);
	});

	// Sort groups by status priority (Running first, then Pending)
	const sortedGroups = [];
	if (groups.Running) sortedGroups.push({ ...groups.Running, status: "Running" });
	if (groups.Pending) sortedGroups.push({ ...groups.Pending, status: "Pending" });

	// Add any other statuses
	Object.entries(groups).forEach(([status, group]) => {
		if (status !== "Running" && status !== "Pending") {
			sortedGroups.push({ ...group, status });
		}
	});

	return sortedGroups;
});

// Handle max workers change with debounce
function onMaxWorkersChange() {
	// Clear existing timeout
	if (updateMaxWorkersTimeout.value) {
		clearTimeout(updateMaxWorkersTimeout.value);
	}

	// Set new timeout for 500ms
	updateMaxWorkersTimeout.value = setTimeout(async () => {
		if (!props.workflowDefinition) return;

		isUpdatingMaxWorkers.value = true;
		try {
			// Update max workers
			await dxWorkflowDefinition.getAction("update").trigger(props.workflowDefinition, {
				max_workers: maxWorkersInput.value
			});

			// Automatically dispatch workers if there are available slots
			if (availableSlots.value > 0 && props.workflowRun) {
				await request.post(`workflow-runs/${props.workflowRun.id}/dispatch-workers`);
			}
		} catch (error) {
			debugError('updating max workers', error);
		} finally {
			isUpdatingMaxWorkers.value = false;
		}
	}, 500);
}

// Load active job dispatches when dialog opens and subscribe to updates
watch(() => props.isShowing, async (showing) => {
	if (!props.workflowRun) return;

	const pusher = usePusher();

	if (showing) {
		debugWebSocketSubscribe(`workflow-${props.workflowRun?.id}`);
		await pusher.subscribeToWorkflowJobDispatches(props.workflowRun);
		pusher.onEvent("JobDispatch", ["updated", "created"], storeObject);
		await loadActiveJobDispatches();
	} else {
		pusher.unsubscribeFromWorkflowJobDispatches();
	}
});

// Update maxWorkersInput when workflowDefinition changes
watch(() => props.workflowDefinition?.max_workers, (newValue) => {
	if (newValue) {
		maxWorkersInput.value = newValue;
	}
});

async function loadActiveJobDispatches() {
	if (!props.workflowRun) return;

	isLoadingJobDispatches.value = true;
	try {
		const response = await request.get(`workflow-runs/${props.workflowRun.id}/active-job-dispatches`);
		activeJobDispatches.value = response;
	} catch (error) {
		debugError('loading active job dispatches', error);
		activeJobDispatches.value = [];
	} finally {
		isLoadingJobDispatches.value = false;
	}
}

async function onDispatchWorkers() {
	if (!props.workflowRun) return;

	isDispatchingWorkers.value = true;
	try {
		await request.post(`workflow-runs/${props.workflowRun.id}/dispatch-workers`);
	} catch (error) {
		debugError('dispatching workers', error);
	} finally {
		isDispatchingWorkers.value = false;
	}
}
</script>
