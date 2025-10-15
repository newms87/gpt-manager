<template>
    <UiCard>
        <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
                Status Timeline
            </h3>
        </template>

        <div class="space-y-3">
            <div
                v-for="status in statusTimeline"
                :key="status.name"
                class="flex items-start space-x-3"
                :class="{ 'opacity-50': status.grayed }"
            >
                <!-- Status Icon with Progress Ring -->
                <div class="relative">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center"
                        :class="status.bgColor"
                    >
                        <QSpinnerHourglass
                            v-if="status.isActive"
                            class="w-5 h-5"
                            color="sky-200"
                            size="lg"
                            bg-active
                        />
                        <component
                            v-else
                            :is="status.icon"
                            class="w-4 h-4"
                            :class="status.textColor"
                        />
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center">
                        <div class="font-medium" :class="status.failed ? 'text-red-700' : 'text-slate-800'">{{
                                status.label
                            }}
                        </div>

                        <div class="ml-4 flex items-center gap-2">
                            <!-- Error Badge -->
                            <ErrorBadge
                                v-if="status.workflowRun"
                                :error-count="status.workflowRun.error_count"
                                :url="dxWorkflowRun.routes.errorsUrl(status.workflowRun)"
                                :animate="status.isActive || status.failed"
                            />

                            <ActionButton
                                v-if="status.workflowRun"
                                type="view"
                                color="sky-invert"
                                size="xs"
                                @click="$emit('view-workflow', status.workflowRun)"
                            />

                            <ActionButton
                                v-if="status.isActive && status.workflowRun"
                                type="stop"
                                color="red"
                                size="xs"
                                tooltip="Stop Workflow"
                                :action="stopWorkflowRunAction"
                                :target="status.workflowRun"
                            />

                            <ActionButton
                                v-if="status.isStopped && status.workflowRun"
                                type="play"
                                color="sky"
                                size="xs"
                                tooltip="Resume Workflow"
                                :action="resumeWorkflowRunAction"
                                :target="status.workflowRun"
                            />

                            <ActionButton
                                v-if="status.name === 'extract-data' && demand?.team_object"
                                type="database"
                                color="green-invert"
                                size="xs"
                                @click="$emit('view-data')"
                            />
                        </div>
                    </div>

                    <p v-if="status.date" class="text-sm text-slate-500">
                        {{ fDateTime(status.date) }}
                    </p>

                    <!-- Runtime Display -->
                    <p v-if="status.runtime" class="text-xs text-slate-400 font-medium">
                        <span v-if="status.isActive">Running for: {{ status.runtime }}</span>
                        <span v-else-if="status.completed">Completed in: {{ status.runtime }}</span>
                        <span v-else-if="status.failed">Failed after: {{ status.runtime }}</span>
                    </p>

                    <QLinearProgress
                        v-if="status.isActive && status.progress != null"
                        size="29px"
                        :value="status.progress / 100"
                        class="w-full rounded bg-slate-300"
                    >
                        <div class="absolute-full flex flex-center">
                            <LabelPillWidget :label="fPercent(status.progress / 100)" color="sky" size="xs" />
                        </div>
                    </QLinearProgress>
                </div>
            </div>
        </div>
    </UiCard>
</template>

<script setup lang="ts">
import ErrorBadge from "@/components/Shared/ErrorBadge.vue";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { FaSolidCheck, FaSolidClock, FaSolidTriangleExclamation } from "danx-icon";
import { ActionButton, DateTime, fDateTime, fDuration, fPercent, LabelPillWidget } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";

const props = defineProps<{
    demand: UiDemand | null;
}>();

const emit = defineEmits<{
    "view-workflow": [workflowRun: any];
    "view-data": [];
}>();

// Get the workflow run actions from the existing dxWorkflowRun controller
const stopWorkflowRunAction = dxWorkflowRun.getAction("stop");
const resumeWorkflowRunAction = dxWorkflowRun.getAction("resume");

// Reactive timer for live updates of running workflows
const currentTime = ref(Date.now());
let intervalId: NodeJS.Timeout | null = null;


// Calculate runtime for a workflow run using fDuration
const calculateRuntime = (workflowRun: any, isActive: boolean): string | null => {
    if (!workflowRun?.started_at) return null;

    let endTime: string | DateTime;

    if (isActive) {
        // For running workflows, use reactive currentTime for live updates
        endTime = DateTime.fromMillis(currentTime.value);
    } else {
        // For completed/failed workflows, use completed_at or failed_at
        const completedAt = workflowRun.completed_at || workflowRun.failed_at;
        if (!completedAt) return null;
        endTime = completedAt;
    }

    return fDuration(workflowRun.started_at, endTime);
};

// Check if there are any active workflows that need live updates
const hasActiveWorkflows = computed(() => {
    if (!props.demand) return false;

    const extractDataActive = props.demand.extract_data_workflow_run?.status &&
        ["Pending", "Running", "Incomplete"].includes(props.demand.extract_data_workflow_run.status);
    const writeMedicalSummaryActive = props.demand.write_medical_summary_workflow_run?.status &&
        ["Pending", "Running", "Incomplete"].includes(props.demand.write_medical_summary_workflow_run.status);
    const writeDemandLetterActive = props.demand.write_demand_letter_workflow_run?.status &&
        ["Pending", "Running", "Incomplete"].includes(props.demand.write_demand_letter_workflow_run.status);

    return extractDataActive || writeMedicalSummaryActive || writeDemandLetterActive;
});

// Setup timer for live updates only when needed
const setupTimer = () => {
    if (!intervalId && hasActiveWorkflows.value) {
        intervalId = setInterval(() => {
            currentTime.value = Date.now();
        }, 1000); // Update every second
    }
};

const clearTimer = () => {
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

// Watch for changes in active workflows to manage timer
watch(hasActiveWorkflows, (hasActive) => {
    if (hasActive) {
        setupTimer();
    } else {
        clearTimer();
    }
}, { immediate: true });

onMounted(() => {
    setupTimer();
});

onUnmounted(() => {
    clearTimer();
});

const statusTimeline = computed(() => {
    if (!props.demand) return [];

    // Helper function to determine workflow state
    const getWorkflowState = (status: string | undefined) => {
        if (!status) return { completed: false, failed: false, active: false, stopped: false };

        const completed = ["Skipped", "Completed"].includes(status);
        const active = ["Pending", "Running", "Incomplete"].includes(status);
        const stopped = status === "Stopped";
        const failed = !completed && !active && !stopped; // Everything else is failed

        return { completed, failed, active, stopped };
    };

    // Get workflow states
    const extractDataState = getWorkflowState(props.demand.extract_data_workflow_run?.status);
    const writeMedicalSummaryState = getWorkflowState(props.demand.write_medical_summary_workflow_run?.status);
    const writeDemandLetterState = getWorkflowState(props.demand.write_demand_letter_workflow_run?.status);
    const hasFiles = props.demand.files && props.demand.files.length > 0;

    // Determine if steps should be grayed out
    const extractDataGrayed = !hasFiles && !props.demand.extract_data_workflow_run;
    const writeMedicalSummaryGrayed = !extractDataState.completed;
    const writeDemandLetterGrayed = !writeMedicalSummaryState.completed;
    const completeGrayed = !writeDemandLetterState.completed;

    // Always show all 5 steps
    return [
        {
            name: "draft",
            label: "Created (Draft)",
            icon: FaSolidClock,
            bgColor: "bg-slate-500",
            textColor: "text-slate-100",
            completed: true,
            failed: false,
            isActive: false,
            progress: null,
            date: props.demand.created_at,
            grayed: false,
            workflowRun: null
        },
        {
            name: "extract-data",
            label: extractDataState.failed ? "Extract Data (Failed)" : "Extract Data",
            icon: extractDataState.completed ? FaSolidCheck : extractDataState.failed ? FaSolidTriangleExclamation : FaSolidClock,
            bgColor: extractDataState.failed ? "bg-red-500" : extractDataState.completed ? "bg-blue-500" : extractDataState.active ? "bg-slate-200" : "bg-gray-400",
            textColor: extractDataState.failed ? "text-red-200" : extractDataState.completed ? "text-blue-200" : "text-gray-200",
            completed: extractDataState.completed,
            failed: extractDataState.failed,
            isActive: extractDataState.active,
            isStopped: extractDataState.stopped,
            progress: extractDataState.active ? props.demand.extract_data_workflow_run?.progress_percent : null,
            date: extractDataState.completed ? props.demand.extract_data_workflow_run?.completed_at : extractDataState.failed ? props.demand.extract_data_workflow_run?.failed_at : null,
            runtime: calculateRuntime(props.demand.extract_data_workflow_run, extractDataState.active),
            grayed: extractDataGrayed,
            workflowRun: props.demand.extract_data_workflow_run
        },
        {
            name: "write-medical-summary",
            label: writeMedicalSummaryState.failed ? "Write Medical Summary (Failed)" : "Write Medical Summary",
            icon: writeMedicalSummaryState.completed ? FaSolidCheck : writeMedicalSummaryState.failed ? FaSolidTriangleExclamation : FaSolidClock,
            bgColor: writeMedicalSummaryState.failed ? "bg-red-500" : writeMedicalSummaryState.completed ? "bg-teal-500" : writeMedicalSummaryState.active ? "bg-slate-200" : "bg-gray-400",
            textColor: writeMedicalSummaryState.failed ? "text-red-200" : writeMedicalSummaryState.completed ? "text-teal-200" : "text-gray-200",
            completed: writeMedicalSummaryState.completed,
            failed: writeMedicalSummaryState.failed,
            isActive: writeMedicalSummaryState.active,
            isStopped: writeMedicalSummaryState.stopped,
            progress: writeMedicalSummaryState.active ? props.demand.write_medical_summary_workflow_run?.progress_percent : null,
            date: writeMedicalSummaryState.completed ? props.demand.write_medical_summary_workflow_run?.completed_at : writeMedicalSummaryState.failed ? props.demand.write_medical_summary_workflow_run?.failed_at : null,
            runtime: calculateRuntime(props.demand.write_medical_summary_workflow_run, writeMedicalSummaryState.active),
            grayed: writeMedicalSummaryGrayed,
            workflowRun: props.demand.write_medical_summary_workflow_run
        },
        {
            name: "write-demand-letter",
            label: writeDemandLetterState.failed ? "Write Demand Letter (Failed)" : "Write Demand Letter",
            icon: writeDemandLetterState.completed ? FaSolidCheck : writeDemandLetterState.failed ? FaSolidTriangleExclamation : FaSolidClock,
            bgColor: writeDemandLetterState.failed ? "bg-red-500" : writeDemandLetterState.completed ? "bg-green-500" : writeDemandLetterState.active ? "bg-slate-200" : "bg-gray-400",
            textColor: writeDemandLetterState.failed ? "text-red-200" : writeDemandLetterState.completed ? "text-green-200" : "text-gray-200",
            completed: writeDemandLetterState.completed,
            failed: writeDemandLetterState.failed,
            isActive: writeDemandLetterState.active,
            isStopped: writeDemandLetterState.stopped,
            progress: writeDemandLetterState.active ? props.demand.write_demand_letter_workflow_run?.progress_percent : null,
            date: writeDemandLetterState.completed ? props.demand.write_demand_letter_workflow_run?.completed_at : writeDemandLetterState.failed ? props.demand.write_demand_letter_workflow_run?.failed_at : null,
            runtime: calculateRuntime(props.demand.write_demand_letter_workflow_run, writeDemandLetterState.active),
            grayed: writeDemandLetterGrayed,
            workflowRun: props.demand.write_demand_letter_workflow_run
        },
        {
            name: "completed",
            label: "Complete",
            icon: FaSolidCheck,
            bgColor: props.demand.status === DEMAND_STATUS.COMPLETED ? "bg-green-600" : "bg-gray-400",
            textColor: props.demand.status === DEMAND_STATUS.COMPLETED ? "text-green-200" : "text-gray-200",
            completed: props.demand.status === DEMAND_STATUS.COMPLETED,
            failed: false,
            isActive: false,
            progress: null,
            date: props.demand.completed_at,
            grayed: completeGrayed,
            workflowRun: null
        }
    ];
});
</script>
