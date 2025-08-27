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
                :key="status.status"
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
                    <div class="flex items-center justify-between">
                        <p class="font-medium" :class="status.failed ? 'text-red-700' : 'text-slate-800'">{{
                                status.label
                            }}</p>
                        <span
                            v-if="status.isActive && status.progress != null"
                            class="text-xs font-semibold text-blue-600 ml-2"
                        >
              {{ status.progress }}%
            </span>
                    </div>

                    <p v-if="status.date" class="text-sm text-slate-500">
                        {{ formatDate(status.date) }}
                    </p>

                    <!-- Progress Bar for Active Workflows -->
                    <div
                        v-if="status.isActive && status.progress != null"
                        class="mt-2 w-full bg-gray-200 rounded-full h-1.5"
                    >
                        <div
                            class="bg-blue-500 h-1.5 rounded-full transition-all duration-300 ease-out"
                            :style="{ width: `${status.progress}%` }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </UiCard>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidClock, FaSolidTriangleExclamation } from "danx-icon";
import { computed } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";

const props = defineProps<{
    demand: UiDemand | null;
}>();

const statusTimeline = computed(() => {
    if (!props.demand) return [];

    // Helper function to determine workflow state
    const getWorkflowState = (status: string | undefined) => {
        if (!status) return { completed: false, failed: false, active: false };

        const completed = ["Skipped", "Completed"].includes(status);
        const active = ["Pending", "Running", "Incomplete"].includes(status);
        const failed = !completed && !active; // Everything else is failed

        return { completed, failed, active };
    };

    // Get workflow states
    const extractDataState = getWorkflowState(props.demand.extract_data_workflow_run?.status);
    const writeDemandState = getWorkflowState(props.demand.write_demand_workflow_run?.status);
    const hasFiles = props.demand.files && props.demand.files.length > 0;

    // Determine if steps should be grayed out
    const extractDataGrayed = !hasFiles && !props.demand.extract_data_workflow_run;
    const writeDemandGrayed = !extractDataState.completed;
    const completeGrayed = !writeDemandState.completed;

    // Always show all 4 steps
    return [
        {
            status: "draft",
            label: "Created (Draft)",
            icon: FaSolidClock,
            bgColor: "bg-slate-500",
            textColor: "text-slate-100",
            completed: true,
            failed: false,
            isActive: false,
            progress: null,
            date: props.demand.created_at,
            grayed: false
        },
        {
            status: "extract-data",
            label: extractDataState.failed ? "Extract Data (Failed)" : "Extract Data",
            icon: extractDataState.completed ? FaSolidCheck : extractDataState.failed ? FaSolidTriangleExclamation : FaSolidClock,
            bgColor: extractDataState.failed ? "bg-red-500" : extractDataState.completed ? "bg-blue-500" : extractDataState.active ? "bg-slate-200" : "bg-gray-400",
            textColor: extractDataState.failed ? "text-red-200" : extractDataState.completed ? "text-blue-200" : "text-gray-200",
            completed: extractDataState.completed,
            failed: extractDataState.failed,
            isActive: extractDataState.active,
            progress: extractDataState.active ? props.demand.extract_data_workflow_run?.progress_percent : null,
            date: extractDataState.completed ? props.demand.extract_data_workflow_run?.completed_at : extractDataState.failed ? props.demand.extract_data_workflow_run?.failed_at : null,
            grayed: extractDataGrayed
        },
        {
            status: "write-demand",
            label: writeDemandState.failed ? "Write Demand (Failed)" : "Write Demand",
            icon: writeDemandState.completed ? FaSolidCheck : writeDemandState.failed ? FaSolidTriangleExclamation : FaSolidClock,
            bgColor: writeDemandState.failed ? "bg-red-500" : writeDemandState.completed ? "bg-green-500" : writeDemandState.active ? "bg-slate-200" : "bg-gray-400",
            textColor: writeDemandState.failed ? "text-red-200" : writeDemandState.completed ? "text-green-200" : "text-gray-200",
            completed: writeDemandState.completed,
            failed: writeDemandState.failed,
            isActive: writeDemandState.active,
            progress: writeDemandState.active ? props.demand.write_demand_workflow_run?.progress_percent : null,
            date: writeDemandState.completed ? props.demand.write_demand_workflow_run?.completed_at : writeDemandState.failed ? props.demand.write_demand_workflow_run?.failed_at : null,
            grayed: writeDemandGrayed
        },
        {
            status: "completed",
            label: "Complete",
            icon: FaSolidCheck,
            bgColor: props.demand.status === DEMAND_STATUS.COMPLETED ? "bg-green-600" : "bg-gray-400",
            textColor: props.demand.status === DEMAND_STATUS.COMPLETED ? "text-green-200" : "text-gray-200",
            completed: props.demand.status === DEMAND_STATUS.COMPLETED,
            failed: false,
            isActive: false,
            progress: null,
            date: props.demand.completed_at,
            grayed: completeGrayed
        }
    ];
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit"
    });
};
</script>
