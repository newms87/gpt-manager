<template>
  <div 
    class="ui-card"
    :class="[
      paddingClasses,
      shadowClasses,
      {
        'hover:shadow-lg cursor-pointer': clickable,
        'border-blue-200 shadow-blue-100/50': selected,
      }
    ]"
    @click="clickable && $emit('click', $event)"
  >
    <header v-if="$slots.header" class="ui-card-header">
      <slot name="header" />
    </header>
    
    <div class="ui-card-content">
      <slot />
    </div>
    
    <footer v-if="$slots.footer" class="ui-card-footer">
      <slot name="footer" />
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  padding?: 'none' | 'sm' | 'md' | 'lg';
  shadow?: 'none' | 'sm' | 'md' | 'lg';
  clickable?: boolean;
  selected?: boolean;
}>(), {
  padding: 'md',
  shadow: 'sm',
  clickable: false,
  selected: false,
});

defineEmits<{
  click: [event: MouseEvent];
}>();

const paddingClasses = computed(() => {
  const paddings = {
    none: '',
    sm: 'p-3',
    md: 'p-4',
    lg: 'p-6',
  };
  return paddings[props.padding];
});

const shadowClasses = computed(() => {
  const shadows = {
    none: '',
    sm: 'shadow-sm',
    md: 'shadow-md',
    lg: 'shadow-lg',
  };
  return shadows[props.shadow];
});
</script>

<style scoped lang="scss">
.ui-card {
  @apply bg-white border border-slate-200/60 rounded-xl transition-all duration-200;
  
  &:hover {
    @apply border-slate-300/60;
  }
}

.ui-card-header {
  @apply border-b border-slate-200/60 -m-4 mb-4 p-4 bg-gradient-to-r from-slate-50/50 to-blue-50/30 rounded-t-xl;
}

.ui-card-footer {
  @apply border-t border-slate-200/60 -m-4 mt-4 p-4 bg-slate-50/50 rounded-b-xl;
}
</style>