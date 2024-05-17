<template>
	<QCard class="bg-slate-800 text-slate-300">
		<div class="flex items-center justify-between p-3">
			<div>
				<a @click="$router.push({name: 'workflows', params: {id: workflowRun.workflow_id}})">
					{{ workflowRun.workflow_name }} ({{ workflowRun.workflow_id }})
				</a>
			</div>
			<div>{{ workflowRun.status }}</div>
			<div>{{ fDateTime(workflowRun.started_at) }}</div>
		</div>
		<div class="flex items-stretch">
			<div class="bg-slate-700 text-slate-300 flex items-center p-3 w-1/3">{{ pendingJobCount }} Pending</div>
			<div class="bg-sky-800 text-sky-200 flex items-center p-3 w-1/3">{{ runningJobCount }} Running</div>
			<div class="w-1/3">
				<div class="bg-lime-900 text-lime-200 px-3 py-1.5">{{ completedJobCount }} Completed</div>
				<div class="bg-red-900 text-red-200 px-3 py-1.5">{{ failedJobCount }} Failed</div>
			</div>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { WorkflowRun } from "@/types/workflows";
import { fDateTime } from "quasar-ui-danx";

const props = defineProps<{
	workflowRun: WorkflowRun;
}>();

const pendingJobCount = props.workflowRun.workflowJobRuns.filter(j => j.status === WORKFLOW_STATUS.PENDING).length;
const runningJobCount = props.workflowRun.workflowJobRuns.filter(j => j.status === WORKFLOW_STATUS.RUNNING).length;
const completedJobCount = props.workflowRun.workflowJobRuns.filter(j => j.status === WORKFLOW_STATUS.COMPLETED).length;
const failedJobCount = props.workflowRun.workflowJobRuns.filter(j => j.status === WORKFLOW_STATUS.FAILED).length;
</script>
