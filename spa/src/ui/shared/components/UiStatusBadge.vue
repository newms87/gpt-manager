<template>
  <span 
    class="ui-status-badge"
    :class="[statusClasses, sizeClasses]"
  >
    <component 
      :is="statusIcon" 
      v-if="showIcon" 
      class="status-icon"
    />
    
    {{ statusText }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { 
  FaSolidClock, 
  FaSolidPlay, 
  FaSolidSpinner, 
  FaSolidCheck, 
  FaSolidXmark 
} from 'danx-icon';

const props = withDefaults(defineProps<{
  status: string;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
}>(), {
  size: 'md',
  showIcon: true,
});

const statusClasses = computed(() => {
  const classes = {
    draft: 'bg-slate-500 text-white border-slate-600',
    ready: 'bg-blue-600 text-white border-blue-700',
    processing: 'bg-amber-500 text-white border-amber-600',
    completed: 'bg-green-600 text-white border-green-700',
    failed: 'bg-red-600 text-white border-red-700',
  };
  // Handle both lowercase and proper case status values
  const status = props.status.toLowerCase();
  return classes[status] || classes.draft;
});

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'px-2 py-1 text-xs',
    md: 'px-3 py-1 text-sm',
    lg: 'px-4 py-2 text-base',
  };
  return sizes[props.size];
});

const statusIcon = computed(() => {
  const icons = {
    draft: FaSolidClock,
    ready: FaSolidPlay,
    processing: FaSolidSpinner,
    completed: FaSolidCheck,
    failed: FaSolidXmark,
  };
  // Handle both lowercase and proper case status values
  const status = props.status.toLowerCase();
  return icons[status] || icons.draft;
});

const statusText = computed(() => {
  // Just display the status as-is since it's already properly cased from backend
  return props.status;
});
</script>

<style scoped lang="scss">
.ui-status-badge {
  @apply inline-flex items-center font-medium rounded-full border;
  @apply transition-all duration-200;
}

.status-icon {
  @apply w-3 h-3 mr-1.5;
  
  .ui-status-badge:has([status="processing"]) & {
    @apply animate-spin;
  }
}
</style>