<template>
  <div class="ui-empty-state">
    <div class="empty-state-icon">
      <component :is="icon" class="w-16 h-16 text-slate-400" />
    </div>
    
    <h3 class="empty-state-title">
      {{ title }}
    </h3>
    
    <p class="empty-state-description">
      {{ description }}
    </p>
    
    <div v-if="$slots.action" class="empty-state-action">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { FaSolidFile } from 'danx-icon';

withDefaults(defineProps<{
  title: string;
  description: string;
  icon?: any;
}>(), {
  icon: FaSolidFile,
});
</script>

<style scoped lang="scss">
.ui-empty-state {
  @apply flex flex-col items-center justify-center py-12 px-6 text-center;
}

.empty-state-icon {
  @apply mb-4;
}

.empty-state-title {
  @apply text-lg font-semibold text-slate-800 mb-2;
}

.empty-state-description {
  @apply text-slate-600 mb-6 max-w-md;
}

.empty-state-action {
  @apply mt-4;
}
</style>