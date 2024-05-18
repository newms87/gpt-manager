<template>
	<div>
		<div class="flex items-stretch">
			<div
				class="flex items-center p-3 w-1/3 cursor-pointer hover:opacity-80"
				:class="{[WORKFLOW_STATUS.PENDING.classPrimary]: true, 'opacity-30': isStatusHidden(WORKFLOW_STATUS.PENDING.value)}"
				@click="filterStatus(WORKFLOW_STATUS.PENDING.value)"
			>{{
					pendingJobs.length
				}} Pending
				<QSpinnerHourglass v-if="pendingJobs.length > 0" class="ml-3" size="sm" />
			</div>
			<div
				class="flex items-center p-3 w-1/3 cursor-pointer hover:opacity-80"
				:class="{[WORKFLOW_STATUS.RUNNING.classPrimary]: true, 'opacity-30': isStatusHidden(WORKFLOW_STATUS.RUNNING.value)}"
				@click="filterStatus(WORKFLOW_STATUS.RUNNING.value)"
			>{{
					runningJobs.length
				}} Running
				<QSpinnerGears v-if="runningJobs.length > 0" class="ml-3" size="sm" />
			</div>
			<div class="w-1/3">
				<div
					class="px-3 py-1.5  cursor-pointer hover:opacity-80"
					:class="{[WORKFLOW_STATUS.COMPLETED.classPrimary]: true, 'opacity-30': isStatusHidden(WORKFLOW_STATUS.COMPLETED.value)}"
					@click="filterStatus(WORKFLOW_STATUS.COMPLETED.value)"
				>{{ completedJobs.length }}
					Completed
				</div>
				<div
					class="px-3 py-1.5  cursor-pointer hover:opacity-80"
					:class="{[WORKFLOW_STATUS.FAILED.classPrimary]: true, 'opacity-30': isStatusHidden(WORKFLOW_STATUS.FAILED.value)}"
					@click="filterStatus(WORKFLOW_STATUS.FAILED.value)"
				>{{ failedJobs.length }}
					Failed
				</div>
			</div>
		</div>
		<div v-if="displayStatus">
			<div v-for="job in displayJobs" :key="job.id" class="m-3">
				<WorkflowJobRunCard :job-run="job" />
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import WorkflowJobRunCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowJobRunCard";
import { WorkflowRun } from "@/types/workflows";
import { computed } from "vue";

const displayStatus = defineModel<string>();

const props = defineProps<{
	workflowRun: WorkflowRun;
}>();

const pendingJobs = computed(() => props.workflowRun.workflowJobRuns?.filter(j => j.status === WORKFLOW_STATUS.PENDING.value));
const runningJobs = computed(() => props.workflowRun.workflowJobRuns?.filter(j => j.status === WORKFLOW_STATUS.RUNNING.value));
const completedJobs = computed(() => props.workflowRun.workflowJobRuns?.filter(j => j.status === WORKFLOW_STATUS.COMPLETED.value));
const failedJobs = computed(() => props.workflowRun.workflowJobRuns?.filter(j => j.status === WORKFLOW_STATUS.FAILED.value));

const displayJobs = computed(() => displayStatus.value === "all" ? props.workflowRun.workflowJobRuns : props.workflowRun.workflowJobRuns?.filter(j => j.status === displayStatus.value));

function filterStatus(status) {
	if (displayStatus.value === status) {
		displayStatus.value = "";
	} else {
		displayStatus.value = status;
	}
}
function isStatusHidden(status) {
	return displayStatus.value && displayStatus.value !== status;
}
</script>
