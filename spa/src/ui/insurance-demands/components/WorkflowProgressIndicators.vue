<template>
    <div v-if="activeWorkflowRuns.length > 0" class="space-y-2">
        <template v-for="workflowRun in activeWorkflowRuns" :key="workflowRun.id">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-600 font-medium">{{ workflowRun.label }}</span>
                <span class="text-blue-600 font-semibold">{{
                        workflowRun.progress_percent || 0
                    }}%</span>
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

const props = defineProps<{
    demand: UiDemand;
}>();

// Dynamic workflow progress indicators
const activeWorkflowRuns = computed(() => {
    if (!props.demand.workflow_runs || !props.demand.workflow_config) return [];

    const colorToProgressBarClass: Record<string, string> = {
        blue: 'bg-blue-500',
        teal: 'bg-teal-500',
        green: 'bg-green-500',
        red: 'bg-red-500',
        orange: 'bg-orange-500',
        purple: 'bg-purple-500',
        slate: 'bg-slate-500'
    };

    return props.demand.workflow_config
        .map(config => {
            const workflowRun = props.demand.workflow_runs[config.key];
            if (!workflowRun) return null;

            const progress = workflowRun.progress_percent;
            if (progress == null || progress <= 0 || progress >= 100) return null;

            return {
                id: workflowRun.id,
                label: config.label,
                progress_percent: progress,
                progressBarClass: colorToProgressBarClass[config.color] || 'bg-blue-500'
            };
        })
        .filter(Boolean);
});
</script>
