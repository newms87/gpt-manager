<template>
    <div v-if="activeWorkflowRuns.length > 0" class="space-y-2">
        <template v-for="workflowRun in activeWorkflowRuns" :key="workflowRun.id">
            <div class="flex items-center justify-between text-xs">
                <span :class="workflowRun.labelClass">{{ workflowRun.label }}</span>
                <span :class="workflowRun.percentClass">{{ workflowRun.progress_percent || 0 }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div
                    class="h-1.5 rounded-full transition-all duration-300 ease-out"
                    :class="workflowRun.progressBarClass"
                    :style="{ width: `${workflowRun.progress_percent || 0}%` }"
                />
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { UiDemand } from "../../shared/types";
import { getWorkflowColors } from "../config";

const props = defineProps<{
    demand: UiDemand;
}>();

// Dynamic workflow progress indicators
const activeWorkflowRuns = computed(() => {
    if (!props.demand.workflow_runs || !props.demand.workflow_config) return [];

    return props.demand.workflow_config
        .map(config => {
            const workflowRun = props.demand.workflow_runs[config.key];
            if (!workflowRun) return null;

            const progress = workflowRun.progress_percent;
            if (progress == null || progress <= 0 || progress >= 100) return null;

            const colors = getWorkflowColors(config.color);

            return {
                id: workflowRun.id,
                label: config.label,
                progress_percent: progress,
                labelClass: `${colors.palette.textPrimary} font-medium`,
                percentClass: `${colors.palette.textPrimary} font-semibold`,
                progressBarClass: colors.progressBarClasses
            };
        })
        .filter(Boolean);
});
</script>
