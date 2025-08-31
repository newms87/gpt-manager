<template>
  <button
    type="button"
    :disabled="loading"
    class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
    :class="buttonClasses"
    @click="$emit('click')"
  >
    <FaSolidDollarSign class="w-3 h-3 mr-1.5" />
    
    <!-- Loading state -->
    <template v-if="loading">
      <div class="animate-spin w-3 h-3 mr-1.5">
        <FaSolidSpinner class="w-3 h-3" />
      </div>
      <span>Loading...</span>
    </template>
    
    <!-- Cost display -->
    <template v-else>
      <span>{{ displayText }}</span>
    </template>
  </button>
</template>

<script setup lang="ts">
import { FaSolidDollarSign, FaSolidSpinner } from "danx-icon";
import { computed } from "vue";
import { fCurrency } from "quasar-ui-danx";

const props = withDefaults(defineProps<{
  cost: number | null | undefined;
  loading?: boolean;
}>(), {
  loading: false
});

defineEmits<{
  click: [];
}>();

const displayText = computed(() => {
  if (props.cost === null || props.cost === undefined) {
    return "No usage";
  }
  
  return fCurrency(props.cost);
});

const buttonClasses = computed(() => {
  const baseClasses = "border border-slate-300 hover:border-slate-400";
  
  if (props.loading) {
    return `${baseClasses} bg-slate-50 text-slate-500 cursor-not-allowed`;
  }
  
  if (props.cost === null || props.cost === undefined || props.cost === 0) {
    return `${baseClasses} bg-slate-50 text-slate-600 hover:bg-slate-100`;
  }
  
  // Apply cost-based coloring
  let costColorClass = "text-slate-600";
  if (props.cost < 0.01) costColorClass = "text-green-600";
  else if (props.cost < 0.10) costColorClass = "text-yellow-600";
  else if (props.cost < 1.00) costColorClass = "text-orange-600";
  else costColorClass = "text-red-600";
  
  return `${baseClasses} bg-white ${costColorClass} hover:bg-slate-50`;
});
</script>