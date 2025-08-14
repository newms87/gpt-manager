<template>
  <UiCard>
    <template #header>
      <h3 class="text-lg font-semibold text-slate-800">
        Demand Details
      </h3>
    </template>

    <div v-if="editMode" class="space-y-4">
      <DemandForm
        mode="edit"
        :initial-data="demand"
        @submit="$emit('update', $event)"
        @cancel="$emit('cancel-edit')"
      />
    </div>

    <div v-else-if="demand" class="space-y-4">
      <div>
        <label class="text-sm font-medium text-slate-700">Title</label>
        <p class="mt-1 text-slate-800">{{ demand.title }}</p>
      </div>

      <div v-if="demand.description">
        <label class="text-sm font-medium text-slate-700">Description</label>
        <p class="mt-1 text-slate-800 whitespace-pre-wrap">{{ demand.description }}</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-medium text-slate-700">Status</label>
          <div class="mt-1">
            <UiStatusBadge :status="demand.status || 'Draft'" />
          </div>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700">Progress</label>
          <div class="mt-1">
            <UiProgressBar
              :value="progressPercentage"
              :color="progressColor"
              size="sm"
              :animated="false"
            />
          </div>
        </div>
      </div>
    </div>
  </UiCard>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { UiCard, UiProgressBar, UiStatusBadge } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DemandForm } from "../";
import { DEMAND_STATUS, getDemandProgressPercentage, getDemandStatusColor } from "../../config";

const props = defineProps<{
  demand: UiDemand | null;
  editMode: boolean;
}>();

defineEmits<{
  'update': [data: { title: string; description: string; files?: any[] }];
  'cancel-edit': [];
}>();

const progressColor = computed(() => {
  if (!props.demand) return "blue";
  return getDemandStatusColor(props.demand.status);
});

const progressPercentage = computed(() => {
  if (!props.demand) return 0;
  return getDemandProgressPercentage(props.demand.status);
});
</script>