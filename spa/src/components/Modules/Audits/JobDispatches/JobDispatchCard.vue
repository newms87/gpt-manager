<template>
	<QCard class="bg-slate-800">
		<div class="flex items-center p-3 flex-nowrap">
			<div class="flex-grow flex items-center flex-nowrap space-x-2">
				<LabelPillWidget :label="`JobDispatch: ${job.id}`" color="sky" size="xs" />
				<LabelPillWidget v-if="job.job_batch_id" :label="`JobBatch: ${job.job_batch_id}`" color="blue" size="xs" />
				<LabelPillWidget :label="job.ref" color="green" size="xs" />
				<div class="text-sm">{{ job.name }}</div>
			</div>
			<div class="flex space-x-2 items-center">
				<ShowHideButton v-model="isShowingLogs" label="Logs" class="bg-slate-950 text-slate-400" />
				<ShowHideButton v-model="isShowingApiLogs" class="bg-sky-900 text-sky-300" label="Api Logs" />
				<ShowHideButton v-model="isShowingErrors" class="bg-red-950 text-red-300" label="Errors" />
				<LabelPillWidget :status="JOB_DISPATCH_STATUS.resolve(job.status)" size="sm" />
			</div>
		</div>
		<div class="p-3 grid grid-cols-7">
			<LabelValueBlock label="Running Request">
				<a @click="$router.push({path: `/audit-requests/${job.running_audit_request_id}/ran-jobs`})">
					{{ job.running_audit_request_id }}
				</a>
			</LabelValueBlock>
			<LabelValueBlock label="Dispatch Request">
				<a @click="$router.push({path: `/audit-requests/${job.dispatch_audit_request_id}/dispatched-jobs`})">
					{{ job.dispatch_audit_request_id }}
				</a>
			</LabelValueBlock>
			<LabelValueBlock label="Created At" :value="fDateTime(job.created_at)" />
			<LabelValueBlock label="Ran At" :value="fDateTime(job.ran_at)" />
			<LabelValueBlock label="Completed At" :value="fDateTime(job.completed_at)" />
			<LabelValueBlock label="Timeout At" :value="fDateTime(job.timeout_at)" />
			<LabelValueBlock label="Run Time" :value="fNumber(+job.run_time)" />
		</div>
		<div v-if="isShowingLogs">
			<AuditRequestLogsCard :logs="job.logs" />
		</div>
	</QCard>
</template>
<script setup lang="ts">
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import AuditRequestLogsCard from "@/components/Modules/Audits/AuditRequestLogs/AuditRequestLogsCard";
import { JOB_DISPATCH_STATUS } from "@/components/Modules/Audits/JobDispatches/statuses";
import LabelPillWidget from "@/components/Shared/Widgets/LabelPillWidget";
import { fDateTime, fNumber, LabelValueBlock, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	job: JobDispatch
}>();

const isShowingLogs = ref(false);
const isShowingApiLogs = ref(false);
const isShowingErrors = ref(false);
</script>
