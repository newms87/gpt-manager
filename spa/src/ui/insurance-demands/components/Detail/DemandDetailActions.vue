<template>
  <div v-if="demand" class="flex items-center space-x-3">
    <!-- Removed redundant buttons - they exist in Quick Actions sidebar -->
  </div>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import type { UiDemand } from "../../../shared/types";
import DemandActionButtons from "../DemandActionButtons.vue";
import { DEMAND_STATUS } from "../../config";

interface LoadingStates {
  extractData: boolean;
  writeDemand: boolean;
}

defineProps<{
  demand: UiDemand | null;
  editMode: boolean;
  loadingStates: LoadingStates;
}>();

defineEmits<{
  'toggle-edit': [];
  'extract-data': [];
  'write-demand': [];
}>();
</script>