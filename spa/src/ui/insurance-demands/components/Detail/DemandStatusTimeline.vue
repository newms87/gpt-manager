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
            :class="status.completed ? status.bgColor : status.isActive ? status.activeBgColor : 'bg-slate-200'"
          >
            <component
              :is="status.icon"
              class="w-4 h-4"
              :class="status.completed || status.isActive ? 'text-white' : 'text-slate-400'"
            />
          </div>
          
          <!-- Progress Ring for Active Workflows -->
          <div 
            v-if="status.isActive && status.progress != null"
            class="absolute inset-0 w-8 h-8"
          >
            <svg class="w-8 h-8 transform -rotate-90" viewBox="0 0 32 32">
              <!-- Background circle -->
              <circle
                cx="16"
                cy="16"
                r="14"
                stroke="currentColor"
                stroke-width="2"
                fill="none"
                class="text-gray-300"
              />
              <!-- Progress circle -->
              <circle
                cx="16"
                cy="16"
                r="14"
                stroke="currentColor"
                stroke-width="2"
                fill="none"
                stroke-linecap="round"
                class="text-blue-500"
                :stroke-dasharray="`${(status.progress / 100) * 87.96} 87.96`"
                style="transition: stroke-dasharray 0.3s ease"
              />
            </svg>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <p class="font-medium text-slate-800">{{ status.label }}</p>
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
import { computed } from "vue";
import { FaSolidCheck, FaSolidClock, FaSolidSpinner } from "danx-icon";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";

const props = defineProps<{
  demand: UiDemand | null;
}>();

const statusTimeline = computed(() => {
  if (!props.demand) return [];

  // Debug logging for reactive updates
  console.log('StatusTimeline computed - demand workflow states:', {
    demand_id: props.demand.id,
    is_extract_data_running: props.demand.is_extract_data_running,
    extract_data_workflow_run: props.demand.extract_data_workflow_run,
    is_write_demand_running: props.demand.is_write_demand_running,
    write_demand_workflow_run: props.demand.write_demand_workflow_run
  });

  // Determine which workflows are active
  const isExtractDataActive = props.demand.is_extract_data_running && 
                             props.demand.extract_data_workflow_run?.progress_percent != null && 
                             props.demand.extract_data_workflow_run.progress_percent > 0 && 
                             props.demand.extract_data_workflow_run.progress_percent < 100;
                             
  const isWriteDemandActive = props.demand.is_write_demand_running && 
                             props.demand.write_demand_workflow_run?.progress_percent != null && 
                             props.demand.write_demand_workflow_run.progress_percent > 0 && 
                             props.demand.write_demand_workflow_run.progress_percent < 100;

  // Determine completion states
  const extractDataCompleted = props.demand.extract_data_workflow_run?.progress_percent === 100;
  const writeDemandCompleted = props.demand.write_demand_workflow_run?.progress_percent === 100;
  const hasFiles = props.demand.files && props.demand.files.length > 0;
  
  // Determine if steps should be grayed out
  const extractDataGrayed = !hasFiles && !props.demand.extract_data_workflow_run;
  const writeDemandGrayed = !extractDataCompleted;
  const completeGrayed = !writeDemandCompleted;
  
  console.log('StatusTimeline activity states:', {
    isExtractDataActive,
    isWriteDemandActive,
    extractDataCompleted,
    writeDemandCompleted,
    extractDataGrayed,
    writeDemandGrayed
  });

  // Always show all 4 steps
  const steps = [
    {
      status: "draft",
      label: "Created (Draft)",
      icon: FaSolidClock,
      bgColor: "bg-slate-500",
      activeBgColor: "bg-slate-400",
      completed: true,
      isActive: false,
      progress: null,
      date: props.demand.created_at,
      grayed: false
    },
    {
      status: "extract-data",
      label: "Extract Data",
      icon: extractDataCompleted ? FaSolidCheck : FaSolidSpinner,
      bgColor: "bg-blue-500",
      activeBgColor: "bg-blue-400",
      completed: extractDataCompleted,
      isActive: isExtractDataActive,
      progress: isExtractDataActive ? props.demand.extract_data_workflow_run?.progress_percent : null,
      date: extractDataCompleted ? props.demand.extract_data_workflow_run?.completed_at : null,
      grayed: extractDataGrayed
    },
    {
      status: "write-demand",
      label: "Write Demand",
      icon: writeDemandCompleted ? FaSolidCheck : FaSolidSpinner,
      bgColor: "bg-green-500", 
      activeBgColor: "bg-green-400",
      completed: writeDemandCompleted,
      isActive: isWriteDemandActive,
      progress: isWriteDemandActive ? props.demand.write_demand_workflow_run?.progress_percent : null,
      date: writeDemandCompleted ? props.demand.write_demand_workflow_run?.completed_at : null,
      grayed: writeDemandGrayed
    },
    {
      status: "completed",
      label: "Complete",
      icon: FaSolidCheck,
      bgColor: "bg-green-500",
      activeBgColor: "bg-green-400",
      completed: props.demand.status === DEMAND_STATUS.COMPLETED,
      isActive: false,
      progress: null,
      date: props.demand.completed_at,
      grayed: completeGrayed
    }
  ];

  return steps;
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