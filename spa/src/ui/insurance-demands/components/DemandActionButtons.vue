<template>
  <div class="flex space-x-2" :class="buttonClass">
    <!-- Extract Data Button -->
    <ActionButton
      type="play"
      :size="size"
      :loading="loadingStates.extractData"
      :label="extractDataLabel"
      :class="buttonItemClass"
      @click="$emit('extract-data')"
    />
    
    <!-- Write Demand Button -->
    <ActionButton
      type="play"
      :size="size"
      :loading="loadingStates.writeDemand"
      :disabled="!canWriteDemand"
      :label="writeDemandLabel"
      :tooltip="writeDemandTooltip"
      :class="buttonItemClass"
      @click="$emit('write-demand')"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ActionButton } from 'quasar-ui-danx';
import type { UiDemand } from '../../shared/types';

interface LoadingStates {
  extractData: boolean;
  writeDemand: boolean;
}

const props = withDefaults(defineProps<{
  demand: UiDemand;
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  buttonClass?: string;
  buttonItemClass?: string;
  extractDataLabel?: string;
  writeDemandLabel?: string;
  loadingStates: LoadingStates;
}>(), {
  size: 'md',
  buttonClass: '',
  buttonItemClass: '',
  extractDataLabel: 'Extract Data',
  writeDemandLabel: 'Write Demand',
});

defineEmits<{
  'extract-data': [];
  'write-demand': [];
}>();

// Computed loading states that combine local loading with demand running states
const loadingStates = computed(() => ({
  extractData: props.loadingStates.extractData || props.demand.is_extract_data_running || false,
  writeDemand: props.loadingStates.writeDemand || props.demand.is_write_demand_running || false,
}));

// Write Demand button state management
const canWriteDemand = computed(() => {
  // Enable Write Demand if extract data workflow is completed (100% progress)
  // OR if extract_data_completed_at exists in metadata (for legacy support)
  return Boolean(
    props.demand.extract_data_workflow_run?.progress_percent === 100 ||
    props.demand.metadata?.extract_data_completed_at
  );
});

const writeDemandTooltip = computed(() => {
  if (!canWriteDemand.value) {
    return 'Extract data first before writing demand';
  }
  return undefined;
});

</script>