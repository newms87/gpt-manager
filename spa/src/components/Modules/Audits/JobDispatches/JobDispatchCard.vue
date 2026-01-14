<template>
    <QCard :class="themeClass('bg-slate-800', 'bg-white shadow-md border border-slate-200')" class="overflow-hidden">
        <!-- Header Section - Horizontal layout -->
        <div class="p-4">
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Status badge -->
                <div
                    :class="[
                        isDark ? jobStatus.classPrimary : jobStatus.classAlt,
                        'px-3 py-1 rounded-lg font-semibold text-xs uppercase tracking-wide'
                    ]"
                >
                    {{ jobStatus.value }}
                </div>

                <!-- Job Name -->
                <h3 :class="themeClass('text-slate-100', 'text-slate-800')" class="text-base font-bold">
                    {{ job.name }}
                </h3>

                <!-- Metadata -->
                <div :class="themeClass('text-slate-400', 'text-slate-500')" class="flex items-center gap-2 text-xs flex-wrap">
                    <span class="font-mono">ref: {{ job.ref }}</span>
                    <span>&#8226;</span>
                    <span>ID: {{ job.id }}</span>
                    <template v-if="job.job_batch_id">
                        <span>&#8226;</span>
                        <span>Batch: {{ job.job_batch_id }}</span>
                    </template>
                    <template v-if="+job.count > 1">
                        <span>&#8226;</span>
                        <span class="font-semibold">Count: {{ job.count }}</span>
                    </template>
                </div>

                <!-- Related Request Links -->
                <div class="flex items-center gap-3 text-xs">
                    <a
                        :href="$router.resolve({ path: `/audit-requests/${job.running_audit_request_id}/ran-jobs` }).href"
                        target="_blank"
                        :class="themeClass('text-green-400 hover:text-green-300', 'text-green-600 hover:text-green-700')"
                        class="flex items-center gap-1 font-mono"
                        title="Running Request"
                    >
                        <PlayIcon class="w-2.5 h-2.5" />
                        <span :class="themeClass('text-slate-400', 'text-slate-500')">run:</span>#{{ job.running_audit_request_id }}
                    </a>
                    <a
                        :href="$router.resolve({ path: `/audit-requests/${job.dispatch_audit_request_id}/dispatched-jobs` }).href"
                        target="_blank"
                        :class="themeClass('text-amber-400 hover:text-amber-300', 'text-amber-600 hover:text-amber-700')"
                        class="flex items-center gap-1 font-mono"
                        title="Dispatch Request"
                    >
                        <SendIcon class="w-2.5 h-2.5" />
                        <span :class="themeClass('text-slate-400', 'text-slate-500')">dispatch:</span>#{{ job.dispatch_audit_request_id }}
                    </a>
                </div>

                <!-- Spacer to push timer to right -->
                <div class="flex-1" />

                <!-- Elapsed Timer -->
                <ElapsedTimer
                    :start-time="job.ran_at"
                    :end-time="job.completed_at"
                    size="sm"
                    :class="{ 'scale-110': isInProgress }"
                />
            </div>
        </div>

        <!-- Horizontal Timeline Section -->
        <div :class="themeClass('bg-slate-900/30 border-slate-700', 'bg-slate-50 border-slate-200')" class="border-t border-b px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Created -->
                <div class="flex flex-col items-center">
                    <div
                        :class="[
                            'w-3 h-3 rounded-full border-2',
                            job.created_at
                                ? themeClass('bg-green-500 border-green-400', 'bg-green-500 border-green-400')
                                : themeClass('bg-slate-700 border-slate-600', 'bg-slate-300 border-slate-400')
                        ]"
                    />
                    <span :class="themeClass('text-slate-300', 'text-slate-600')" class="text-xs font-medium mt-1">Created</span>
                    <span :class="themeClass('text-slate-500', 'text-slate-400')" class="text-xs font-mono">
                        {{ job.created_at ? fTime(job.created_at) : '-' }}
                        <QTooltip v-if="job.created_at">{{ fDateTimeMs(job.created_at) }}</QTooltip>
                    </span>
                </div>

                <!-- Line connector -->
                <div :class="themeClass('bg-slate-600', 'bg-slate-300')" class="flex-1 h-0.5 mx-2" />

                <!-- Started -->
                <div class="flex flex-col items-center">
                    <div
                        :class="[
                            'w-3 h-3 rounded-full border-2',
                            job.ran_at
                                ? themeClass('bg-green-500 border-green-400', 'bg-green-500 border-green-400')
                                : themeClass('bg-slate-700 border-slate-600', 'bg-slate-300 border-slate-400')
                        ]"
                    />
                    <span :class="themeClass('text-slate-300', 'text-slate-600')" class="text-xs font-medium mt-1">Started</span>
                    <span :class="themeClass('text-slate-500', 'text-slate-400')" class="text-xs font-mono">
                        {{ job.ran_at ? fTime(job.ran_at) : '-' }}
                        <QTooltip v-if="job.ran_at">{{ fDateTimeMs(job.ran_at) }}</QTooltip>
                    </span>
                </div>

                <!-- Line connector -->
                <div :class="themeClass('bg-slate-600', 'bg-slate-300')" class="flex-1 h-0.5 mx-2" />

                <!-- Completed -->
                <div class="flex flex-col items-center">
                    <div
                        :class="[
                            'w-3 h-3 rounded-full border-2',
                            job.completed_at
                                ? themeClass('bg-green-500 border-green-400', 'bg-green-500 border-green-400')
                                : themeClass('bg-slate-700 border-slate-600', 'bg-slate-300 border-slate-400')
                        ]"
                    />
                    <span :class="themeClass('text-slate-300', 'text-slate-600')" class="text-xs font-medium mt-1">Completed</span>
                    <span :class="themeClass('text-slate-500', 'text-slate-400')" class="text-xs font-mono">
                        <template v-if="job.completed_at">
                            {{ fTime(job.completed_at) }}
                            <span v-if="job.run_time_ms" :class="themeClass('text-sky-400', 'text-sky-600')">
                                ({{ fMillisecondsToDuration(+job.run_time_ms) }})
                            </span>
                            <QTooltip>{{ fDateTimeMs(job.completed_at) }}</QTooltip>
                        </template>
                        <template v-else>-</template>
                    </span>
                </div>

                <!-- Line connector -->
                <div :class="themeClass('bg-slate-600', 'bg-slate-300')" class="flex-1 h-0.5 mx-2 border-dashed" />

                <!-- Timeout -->
                <div class="flex flex-col items-center">
                    <div
                        :class="[
                            'w-3 h-3 rounded-full border-2 border-dashed',
                            themeClass('bg-slate-700 border-slate-500', 'bg-slate-200 border-slate-400')
                        ]"
                    />
                    <span :class="themeClass('text-slate-500', 'text-slate-400')" class="text-xs font-medium mt-1">Timeout</span>
                    <span :class="themeClass('text-slate-600', 'text-slate-400')" class="text-xs font-mono">
                        {{ job.timeout_at ? fTime(job.timeout_at) : '-' }}
                        <QTooltip v-if="job.timeout_at">{{ fDateTimeMs(job.timeout_at) }}</QTooltip>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Bar - At the bottom -->
        <div :class="themeClass('bg-slate-900/50 border-slate-700', 'bg-slate-50 border-slate-200')">
            <div class="flex items-center divide-x" :class="themeClass('divide-slate-700', 'divide-slate-200')">
                <button
                    :class="[
                        'flex items-center gap-2 px-4 py-3 transition-colors flex-1 justify-center',
                        isShowingLogs
                            ? themeClass('bg-slate-700 text-slate-200', 'bg-slate-200 text-slate-700')
                            : themeClass('hover:bg-slate-700/50 text-slate-400', 'hover:bg-slate-100 text-slate-600')
                    ]"
                    @click="isShowingLogs = !isShowingLogs"
                >
                    <LogIcon class="w-4 h-4" />
                    <span class="font-medium">Logs</span>
                    <span :class="themeClass('bg-slate-600 text-slate-200', 'bg-slate-300 text-slate-700')" class="px-2 py-0.5 rounded-full text-xs font-semibold">
                        {{ job.log_line_count }}
                    </span>
                </button>

                <button
                    :class="[
                        'flex items-center gap-2 px-4 py-3 transition-colors flex-1 justify-center',
                        isShowingApiLogs
                            ? themeClass('bg-sky-900 text-sky-200', 'bg-sky-100 text-sky-700')
                            : themeClass('hover:bg-sky-900/30 text-sky-400', 'hover:bg-sky-50 text-sky-600')
                    ]"
                    @click="isShowingApiLogs = !isShowingApiLogs"
                >
                    <ApiIcon class="w-4 h-4" />
                    <span class="font-medium">API Calls</span>
                    <span :class="themeClass('bg-sky-800 text-sky-200', 'bg-sky-200 text-sky-800')" class="px-2 py-0.5 rounded-full text-xs font-semibold">
                        {{ job.api_log_count }}
                    </span>
                </button>

                <button
                    :class="[
                        'flex items-center gap-2 px-4 py-3 transition-colors flex-1 justify-center',
                        isShowingErrors
                            ? themeClass('bg-red-900 text-red-200', 'bg-red-100 text-red-700')
                            : job.error_log_count > 0
                                ? themeClass('bg-red-950/50 text-red-400', 'bg-red-50 text-red-600')
                                : themeClass('hover:bg-slate-700/50 text-slate-400', 'hover:bg-slate-100 text-slate-600')
                    ]"
                    @click="isShowingErrors = !isShowingErrors"
                >
                    <ErrorIcon class="w-4 h-4" />
                    <span class="font-medium">Errors</span>
                    <span
                        :class="[
                            'px-2 py-0.5 rounded-full text-xs font-semibold',
                            job.error_log_count > 0
                                ? themeClass('bg-red-800 text-red-200', 'bg-red-200 text-red-800')
                                : themeClass('bg-slate-600 text-slate-200', 'bg-slate-300 text-slate-700')
                        ]"
                    >
                        {{ job.error_log_count }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Expandable Sections -->
        <QSlideTransition>
            <div v-if="isShowingLogs" :class="[themeClass('bg-slate-900 border-slate-700', 'bg-slate-100 border-slate-200'), 'border-t']">
                <div :class="themeClass('bg-slate-800 border-slate-700', 'bg-white border-slate-200')" class="border-b px-4 py-2 flex items-center justify-between">
                    <span :class="themeClass('text-slate-300', 'text-slate-700')" class="font-medium text-sm">Logs</span>
                    <ActionButton
                        type="cancel"
                        color="slate"
                        size="xs"
                        tooltip="Close logs"
                        @click="isShowingLogs = false"
                    />
                </div>
                <AuditRequestLogsCard :logs="job.logs" />
            </div>
        </QSlideTransition>

        <QSlideTransition>
            <div v-if="isShowingApiLogs" :class="themeClass('bg-slate-900', 'bg-slate-100')">
                <div :class="themeClass('bg-slate-800 border-slate-700', 'bg-white border-slate-200')" class="border-b px-4 py-2 flex items-center justify-between">
                    <span :class="themeClass('text-sky-300', 'text-sky-700')" class="font-medium text-sm">API Logs</span>
                    <ActionButton
                        type="cancel"
                        color="slate"
                        size="xs"
                        tooltip="Close API logs"
                        @click="isShowingApiLogs = false"
                    />
                </div>
                <div class="p-3">
                    <QSkeleton v-if="isLoadingApiLogs" class="h-24" />
                    <ListTransition v-else>
                        <ApiLogEntryCard v-for="apiLog in job.apiLogs" :key="apiLog.id" :api-log="apiLog" class="mb-2 last:mb-0" />
                    </ListTransition>
                    <div v-if="!isLoadingApiLogs && (!job.apiLogs || job.apiLogs.length === 0)" :class="themeClass('text-slate-500', 'text-slate-400')" class="text-center py-4 text-sm">
                        No API logs recorded
                    </div>
                </div>
            </div>
        </QSlideTransition>

        <QSlideTransition>
            <div v-if="isShowingErrors" :class="themeClass('bg-slate-900', 'bg-slate-100')">
                <div :class="themeClass('bg-red-950 border-red-900', 'bg-red-50 border-red-200')" class="border-b px-4 py-2 flex items-center justify-between">
                    <span :class="themeClass('text-red-300', 'text-red-700')" class="font-medium text-sm">Errors</span>
                    <ActionButton
                        type="cancel"
                        color="slate"
                        size="xs"
                        tooltip="Close errors"
                        @click="isShowingErrors = false"
                    />
                </div>
                <div class="p-3">
                    <QSkeleton v-if="isLoadingErrors" class="h-24" />
                    <ListTransition v-else>
                        <ErrorLogEntryCard v-for="error in job.errors" :key="error.id" :error="error" class="mb-2 last:mb-0" />
                    </ListTransition>
                    <div v-if="!isLoadingErrors && (!job.errors || job.errors.length === 0)" :class="themeClass('text-slate-500', 'text-slate-400')" class="text-center py-4 text-sm">
                        No errors recorded
                    </div>
                </div>
            </div>
        </QSlideTransition>
    </QCard>
</template>

<script setup lang="ts">
import ApiLogEntryCard from "@/components/Modules/Audits/ApiLogs/ApiLogEntryCard";
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import AuditRequestLogsCard from "@/components/Modules/Audits/AuditRequestLogs/AuditRequestLogsCard";
import ErrorLogEntryCard from "@/components/Modules/Audits/ErrorLogs/ErrorLogEntryCard";
import { jobDispatchRoutes } from "@/components/Modules/Audits/JobDispatches/jobDispatchRoutes";
import { JOB_DISPATCH_STATUS } from "@/components/Modules/Audits/JobDispatches/statuses";
import { useJobDispatchUpdates } from "@/components/Modules/Audits/JobDispatches/useJobDispatchUpdates";
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import ElapsedTimer from "@/components/Shared/ElapsedTimer.vue";
import {
    FaSolidFileLines as LogIcon,
    FaSolidPaperPlane as SendIcon,
    FaSolidPlay as PlayIcon,
    FaSolidPlug as ApiIcon,
    FaSolidTriangleExclamation as ErrorIcon
} from "danx-icon";
import { ActionButton, fDateTime, fDateTimeMs, fMillisecondsToDuration, ListTransition } from "quasar-ui-danx";
import { QSkeleton, QSlideTransition, QTooltip } from "quasar";
import { computed, ref, watch } from "vue";

const props = defineProps<{
    job: JobDispatch
}>();

const { isDark, themeClass } = useAuditCardTheme();

const isShowingLogs = ref(false);
const isShowingApiLogs = ref(false);
const isShowingErrors = ref(false);
const isLoadingApiLogs = ref(false);
const isLoadingErrors = ref(false);

const jobStatus = computed(() => JOB_DISPATCH_STATUS.resolve(props.job.status));
const isInProgress = computed(() => props.job.status === "Running");

// Format time with seconds (e.g., "10:30:45am")
function fTime(dateTime: string | null) {
    return fDateTime(dateTime, { format: "h:mm:ssa" });
}

// Subscribe to real-time updates for in-progress job dispatches
useJobDispatchUpdates(() => props.job);

// Lazy load API logs when the user toggles them on
watch(isShowingApiLogs, async (isShowing) => {
    if (isShowing && !props.job.apiLogs) {
        isLoadingApiLogs.value = true;
        await jobDispatchRoutes.details(props.job, { apiLogs: true });
        isLoadingApiLogs.value = false;
    }
});

// Lazy load errors when the user toggles them on
watch(isShowingErrors, async (isShowing) => {
    if (isShowing && !props.job.errors) {
        isLoadingErrors.value = true;
        await jobDispatchRoutes.details(props.job, { errors: true });
        isLoadingErrors.value = false;
    }
});
</script>
