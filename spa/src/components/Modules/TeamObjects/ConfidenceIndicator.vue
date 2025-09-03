<template>
  <div class="flex items-center gap-1">
    <component 
      :is="confidenceIcon" 
      :class="confidenceIconClass" 
      class="w-3 h-3" 
    />
    <span 
      :class="confidenceTextClass" 
      class="text-xs font-medium"
    >
      {{ confidenceLabel }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { 
  FaSolidCheck as HighIcon,
  FaSolidTriangleExclamation as MediumIcon,
  FaSolidX as LowIcon,
  FaSolidQuestion as NoneIcon
} from 'danx-icon';

const props = withDefaults(defineProps<{
  confidence: string | null | undefined;
  showLabel?: boolean;
}>(), {
  showLabel: true
});

const confidenceLevel = computed(() => {
  if (!props.confidence) return 'none';
  
  const conf = props.confidence.toLowerCase();
  if (conf === 'high') return 'high';
  if (conf === 'medium') return 'medium';
  if (conf === 'low') return 'low';
  return 'none';
});

const confidenceIcon = computed(() => {
  switch (confidenceLevel.value) {
    case 'high': return HighIcon;
    case 'medium': return MediumIcon;
    case 'low': return LowIcon;
    default: return NoneIcon;
  }
});

const confidenceIconClass = computed(() => {
  switch (confidenceLevel.value) {
    case 'high': return 'text-green-500';
    case 'medium': return 'text-amber-500';
    case 'low': return 'text-red-500';
    default: return 'text-slate-400';
  }
});

const confidenceTextClass = computed(() => {
  switch (confidenceLevel.value) {
    case 'high': return 'text-green-600';
    case 'medium': return 'text-amber-600';
    case 'low': return 'text-red-600';
    default: return 'text-slate-500';
  }
});

const confidenceLabel = computed(() => {
  if (!props.showLabel) return '';
  
  switch (confidenceLevel.value) {
    case 'high': return 'High';
    case 'medium': return 'Medium';
    case 'low': return 'Low';
    default: return 'None';
  }
});
</script>