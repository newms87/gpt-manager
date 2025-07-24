<template>
  <span 
    class="ui-status-badge"
    :class="[statusClasses, sizeClasses]"
  >
    <component 
      v-if="showIcon" 
      :is="statusIcon" 
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
    draft: 'bg-slate-100 text-slate-700 border-slate-200',
    ready: 'bg-blue-100 text-blue-700 border-blue-200',
    processing: 'bg-amber-100 text-amber-700 border-amber-200',
    completed: 'bg-green-100 text-green-700 border-green-200',
    failed: 'bg-red-100 text-red-700 border-red-200',
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