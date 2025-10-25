<template>
  <div class="px-6 py-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">
          {{ demand?.title || "Loading..." }}
        </h1>
        <div v-if="demand?.status" class="flex items-center space-x-4 mt-1">
          <UiStatusBadge :status="demand.status" />
          <span v-if="demand.created_at" class="text-sm text-slate-500">
            Created {{ formatDate(demand.created_at) }}
          </span>
        </div>
      </div>

      <div class="flex items-center space-x-4">
        <slot name="actions" />

        <RouterLink to="/ui/demands">
          <ActionButton
            type="back"
            size="sm"
            label="View All Demands"
          />
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import { RouterLink } from "vue-router";
import { UiStatusBadge } from "../../../shared";
import type { UiDemand } from "../../../shared/types";

defineProps<{
  demand: UiDemand | null;
}>();

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