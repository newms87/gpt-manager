<template>
  <div class="ui-loading-spinner" :class="sizeClasses">
    <div class="spinner-ring"></div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg' | 'xl';
}>(), {
  size: 'md',
});

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'w-4 h-4',
    md: 'w-6 h-6',
    lg: 'w-8 h-8',
    xl: 'w-12 h-12',
  };
  return sizes[props.size];
});
</script>

<style scoped lang="scss">
.ui-loading-spinner {
  @apply inline-block;
}

.spinner-ring {
  @apply w-full h-full border-2 border-current border-t-transparent rounded-full animate-spin;
  opacity: 0.8;
}
</style>