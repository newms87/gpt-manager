<template>
  <div class="ui-progress-wrapper">
    <div 
      v-if="showLabel" 
      class="flex justify-between items-center mb-2"
    >
      <span class="text-sm font-medium text-slate-700">
        {{ label }}
      </span>
      <span class="text-sm text-slate-500">
        {{ value }}{{ showPercentage ? '%' : '' }}
      </span>
    </div>
    
    <div 
      class="ui-progress-track"
      :class="sizeClasses"
    >
      <div 
        class="ui-progress-fill"
        :class="colorClasses"
        :style="{ width: `${Math.min(value, 100)}%` }"
      >
        <div v-if="animated" class="progress-shimmer"></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  value: number;
  label?: string;
  color?: 'blue' | 'green' | 'amber' | 'red' | 'purple';
  size?: 'sm' | 'md' | 'lg';
  animated?: boolean;
  showLabel?: boolean;
  showPercentage?: boolean;
}>(), {
  color: 'blue',
  size: 'md',
  animated: false,
  showLabel: true,
  showPercentage: true,
});

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'h-1',
    md: 'h-2',
    lg: 'h-3',
  };
  return sizes[props.size];
});

const colorClasses = computed(() => {
  const colors = {
    blue: 'bg-gradient-to-r from-blue-500 to-blue-600',
    green: 'bg-gradient-to-r from-green-500 to-green-600',
    amber: 'bg-gradient-to-r from-amber-500 to-amber-600',
    red: 'bg-gradient-to-r from-red-500 to-red-600',
    purple: 'bg-gradient-to-r from-purple-500 to-purple-600',
  };
  return colors[props.color];
});
</script>

<style scoped lang="scss">
.ui-progress-wrapper {
  @apply w-full;
}

.ui-progress-track {
  @apply w-full bg-slate-200 rounded-full overflow-hidden;
}

.ui-progress-fill {
  @apply h-full rounded-full transition-all duration-500 ease-out relative;
}

.progress-shimmer {
  @apply absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent;
  animation: shimmer 2s infinite;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}
</style>