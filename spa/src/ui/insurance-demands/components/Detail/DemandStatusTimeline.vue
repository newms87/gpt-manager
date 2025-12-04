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
                            <!-- Run Workflow Button (first) -->
                            <WorkflowRunButton
                                v-if="status.config && canRunWorkflow(status)"
                                :config="status.config"
                                :demand="demand"
                                :color="getWorkflowButtonColor(status.config)"
                                :tooltip="getRunButtonTooltip(status)"
                                @run="(key, params) => $emit('run-workflow', key, params)"
                            />

                            <!-- Stop Button (when running) -->
                            <ActionButton
                                v-if="status.isActive && status.workflowRun"
                                type="stop"
                                color="red"
                                size="xs"
                                tooltip="Stop Workflow"
                                :action="stopWorkflowRunAction"
                                :target="status.workflowRun"
                            />

                            <!-- Resume Button (when stopped) -->
                            <ActionButton
                                v-if="status.isStopped && status.workflowRun"
                                type="play"
                                color="sky"
                                size="xs"
                                tooltip="Resume Workflow"
                                :action="resumeWorkflowRunAction"
                                :target="status.workflowRun"
                            />

                            <!-- Error Badge -->
                            <ErrorBadge
                                v-if="status.workflowRun"
                                :error-count="status.workflowRun.error_count"
                                :url="dxWorkflowRun.routes.errorsUrl(status.workflowRun)"
                                :animate="status.isActive || status.failed"
                            />

                            <!-- View Workflow Button -->
                            <ActionButton
                                v-if="status.workflowRun"
                                type="view"
                                color="sky-invert"
                                size="xs"
                                @click="$emit('view-workflow', status.workflowRun)"
                            />

                            <!-- View Data Button -->
                            <ActionButton
                                v-if="status.extractsData && demand?.team_object"
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
import { ActionButton, fDateTime, fPercent, LabelPillWidget } from "quasar-ui-danx";
import { computed, toRef } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand, WorkflowConfig } from "../../../shared/types";
import { isWorkflowActive, useActiveWorkflowTimer, useWorkflowStatusTimeline } from "../../composables";
import WorkflowRunButton from "../WorkflowRunButton.vue";

const props = defineProps<{
    demand: UiDemand | null;
}>();

const emit = defineEmits<{
    "view-workflow": [workflowRun: any];
    "view-data": [];
    "run-workflow": [workflowKey: string, parameters?: Record<string, any>];
}>();

// Get the workflow run actions from the existing dxWorkflowRun controller
const stopWorkflowRunAction = dxWorkflowRun.getAction("stop");
const resumeWorkflowRunAction = dxWorkflowRun.getAction("resume");

// Check if there are any active workflows that need live updates
const hasActiveWorkflows = computed(() => {
    if (!props.demand?.workflow_runs) return false;

    // Check if any workflow is active
    return Object.values(props.demand.workflow_runs).some(workflowRun =>
        isWorkflowActive(workflowRun)
    );
});

// Use composables for timer and status timeline
const { currentTime } = useActiveWorkflowTimer(hasActiveWorkflows);
const { statusTimeline } = useWorkflowStatusTimeline(toRef(props, 'demand'), currentTime);

// Check if a workflow can run
const canRunWorkflow = (status: any): boolean => {
    // Cannot run if no config (draft/completed statuses don't have config)
    if (!status.config) return false;

    // Cannot run if already running
    if (status.isActive) return false;

    // Cannot run if dependencies not met
    if (status.grayed) return false;

    // Can run (or re-run) any workflow when dependencies are met
    return true;
};

// Get workflow button color from config
const getWorkflowButtonColor = (config: WorkflowConfig): string => {
    const colorMap: Record<string, string> = {
        blue: "sky",
        teal: "teal",
        green: "green",
        red: "red",
        orange: "orange",
        purple: "purple",
        slate: "slate"
    };
    return colorMap[config.color] || config.color;
};

// Get run button tooltip
const getRunButtonTooltip = (status: any): string => {
    if (status.isActive) {
        return `${status.config?.label || status.label} is currently running`;
    }
    if (status.failed) {
        return `Retry ${status.config?.label || status.label}`;
    }
    if (status.completed) {
        return `Re-run ${status.config?.label || status.label}`;
    }
    return `Run ${status.config?.label || status.label}`;
};
</script>
