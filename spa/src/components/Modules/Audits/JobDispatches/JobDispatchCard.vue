<template>
    <QCard class="bg-slate-800">
        <div class="flex items-center p-3 flex-nowrap">
            <div class="flex-grow flex-x space-x-2">
                <LabelPillWidget :label="`JobDispatch: ${job.id}`" color="sky" size="xs" />
                <LabelPillWidget
                    v-if="job.job_batch_id"
                    :label="`JobBatch: ${job.job_batch_id}`"
                    color="blue"
                    size="xs"
                />
                <LabelPillWidget :label="job.ref" color="green" size="xs" />
                <div class="text-sm">{{ job.name }}</div>
            </div>
            <div class="flex space-x-2 items-center">
                <ShowHideButton v-model="isShowingLogs" label="Logs" class="bg-slate-950 text-slate-400" />
                <ShowHideButton
                    v-if="job.apiLogs"
                    v-model="isShowingApiLogs"
                    class="bg-sky-900 text-sky-300"
                    :label="`Api Logs: ${job.apiLogs.length}`"
                />
                <ShowHideButton
                    v-if="job.errors"
                    v-model="isShowingErrors"
                    class="bg-red-950 text-red-300"
                    :label="`Errors: ${job.errors.length}`"
                />
                <LabelPillWidget :label="jobStatus.value" :class="jobStatus.classPrimary" size="sm" />
            </div>
        </div>
        <div class="p-3 grid grid-cols-7">
            <LabelValueBlock label="Running Request">
                <a
                    :href="$router.resolve({path: `/audit-requests/${job.running_audit_request_id}/ran-jobs`}).href"
                    target="_blank"
                >
                    {{ job.running_audit_request_id }}
                </a>
            </LabelValueBlock>
            <LabelValueBlock label="Dispatch Request">
                <a
                    :href="$router.resolve({path: `/audit-requests/${job.dispatch_audit_request_id}/dispatched-jobs`}).href"
                    target="_blank"
                >
                    {{ job.dispatch_audit_request_id }}
                </a>
            </LabelValueBlock>
            <LabelValueBlock label="Created At" :value="fDateTime(job.created_at)" />
            <LabelValueBlock label="Ran At" :value="fDateTime(job.ran_at)" />
            <LabelValueBlock label="Completed At" :value="fDateTime(job.completed_at)" />
            <LabelValueBlock label="Timeout At" :value="fDateTime(job.timeout_at)" />
            <LabelValueBlock label="Run Time" :value="fMillisecondsToDuration(+job.run_time_ms)" />
        </div>
        <AuditRequestLogsCard v-if="isShowingLogs" :logs="job.logs" />
        <ListTransition v-if="isShowingApiLogs">
            <ApiLogEntryCard v-for="apiLog in job.apiLogs" :key="apiLog.id" :api-log="apiLog" class="my-2" />
        </ListTransition>
        <ListTransition v-if="isShowingErrors">
            <ErrorLogEntryCard v-for="error in job.errors" :key="error.id" :error="error" class="my-2" />
        </ListTransition>
    </QCard>
</template>
<script setup lang="ts">
import ApiLogEntryCard from "@/components/Modules/Audits/ApiLogs/ApiLogEntryCard";
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import AuditRequestLogsCard from "@/components/Modules/Audits/AuditRequestLogs/AuditRequestLogsCard";
import ErrorLogEntryCard from "@/components/Modules/Audits/ErrorLogs/ErrorLogEntryCard";
import { JOB_DISPATCH_STATUS } from "@/components/Modules/Audits/JobDispatches/statuses";
import {
    fDateTime,
    fMillisecondsToDuration,
    LabelPillWidget,
    LabelValueBlock,
    ListTransition,
    ShowHideButton
} from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
    job: JobDispatch
}>();

const isShowingLogs = ref(false);
const isShowingApiLogs = ref(false);
const isShowingErrors = ref(false);
const jobStatus = computed(() => JOB_DISPATCH_STATUS.resolve(props.job.status));
</script>
