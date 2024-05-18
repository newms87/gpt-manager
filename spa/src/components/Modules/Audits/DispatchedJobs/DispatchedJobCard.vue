<template>
	<QCard class="bg-slate-800">
		<div class="flex items-center p-3 flex-nowrap">
			<div class="flex-grow flex items-center flex-nowrap">
				<div class="text-lg">{{ job.name }} ({{ job.id }})</div>
				<div class="ml-4">{{ job.ref }}</div>
				<div v-if="job.job_batch_id">Batch: {{ job.job_batch_id }}</div>
			</div>
			<div class="p-2">{{ job.status }}</div>
		</div>
		<div class="p-3">
			<div>
				<a @click="$router.push({path: `/audit-requests/${job.running_audit_request_id}/ran-jobs`})">
					Running Audit Request ({{ job.running_audit_request_id }})
				</a>
			</div>
			<div>
				<a @click="$router.push({path: `/audit-requests/${job.dispatch_audit_request_id}/dispatched-jobs`})">
					Dispatch Audit Request ({{ job.dispatch_audit_request_id }})
				</a>
			</div>
			<div v-if="job.ran_at">
				Ran at {{ fDateTime(job.ran_at) }}
			</div>
			<div v-if="job.completed_at">
				Completed At {{ fDateTime(job.completed_at) }}
			</div>
			<div v-if="job.timeout_at">
				Timeout At {{ fDateTime(job.timeout_at) }}
			</div>
			<div>
				Created At {{ fDateTime(job.created_at) }}
			</div>
			<div>
				Timing: {{ fNumber(+job.run_time) }}s
			</div>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import { fDateTime, fNumber } from "quasar-ui-danx";

defineProps<{
	job: JobDispatch
}>();
</script>
