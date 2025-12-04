<template>
    <UiCard
        clickable
        class="demand-card hover:shadow-xl transition-all duration-300"
        @click="$emit('view')"
    >
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-slate-800 truncate">
                        {{ demand.title }}
                    </h3>
                    <p v-if="demand.description" class="text-sm text-slate-600 mt-1 line-clamp-2">
                        {{ demand.description }}
                    </p>
                </div>

                <UiStatusBadge :status="demand.status" class="ml-4 flex-shrink-0" />
            </div>

            <!-- Progress Bar -->
            <UiProgressBar
                :value="progressPercentage"
                :color="progressColor"
                :label="`Progress: ${progressPercentage}%`"
                size="sm"
                :animated="hasActiveWorkflows"
            />

            <!-- File Count, Dates & Usage -->
            <div class="flex items-center justify-between text-sm text-slate-500">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <FaSolidPaperclip class="w-4 h-4 mr-1" />
                        <span>{{ demand.input_files_count || 0 }} files</span>
                    </div>

                    <div class="flex items-center">
                        <FaSolidClock class="w-4 h-4 mr-1" />
                        <span>{{ formatDate(demand.created_at) }}</span>
                    </div>
                    
                    <!-- Usage Cost Button -->
                    <UsageCostButton
                        :cost="demand.usage_summary?.total_cost || null"
                        @click.stop="handleUsageClick"
                    />
                </div>

                <div v-if="demand.completed_at" class="text-green-600 font-medium">
                    Completed {{ formatDate(demand.completed_at) }}
                </div>
            </div>

            <!-- Workflow Progress Indicators -->
            <WorkflowProgressIndicators :demand="demand" />
        </div>
    </UiCard>
</template>

<script setup lang="ts">
import { FaSolidClock, FaSolidPaperclip } from "danx-icon";
import { computed } from "vue";
import { UiCard, UiProgressBar, UiStatusBadge } from "../../shared/components";
import type { UiDemand } from "../../shared/types";
import { getDemandProgressPercentage, getDemandStatusColor } from "../config";
import { UsageCostButton } from "./Usage";
import WorkflowProgressIndicators from "./WorkflowProgressIndicators.vue";

const props = defineProps<{
    demand: UiDemand;
}>();

const emit = defineEmits<{
    edit: [];
    view: [];
    'view-usage': [];
}>();

const progressColor = computed(() => getDemandStatusColor(props.demand.status));
const progressPercentage = computed(() => getDemandProgressPercentage(props.demand.status));

const hasActiveWorkflows = computed(() => {
    if (!props.demand.workflow_runs || !props.demand.workflow_config) return false;

    return props.demand.workflow_config.some(config => {
        const workflowRun = props.demand.workflow_runs[config.key];
        if (!workflowRun) return false;

        const progress = workflowRun.progress_percent;
        return progress != null && progress > 0 && progress < 100;
    });
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric"
    });
};

const handleUsageClick = () => {
    emit('view-usage');
};
</script>

<style scoped lang="scss">
.demand-card {
    &:hover {
        transform: translateY(-1px);
    }
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
