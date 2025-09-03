<template>
  <div 
    class="bg-slate-750 border border-slate-600 rounded-lg p-4 hover:shadow-lg transition-all duration-200 hover:border-slate-500 relative cursor-pointer"
    @click="onSourcesClick"
  >
    <div class="flex items-start justify-between mb-2">
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-semibold text-slate-200 truncate">
          {{ attribute.name }}
        </h4>
      </div>
      
      <div class="flex items-center gap-2 ml-3">
        <ConfidenceIndicator 
          :confidence="attribute.confidence"
          :show-label="false"
        />
        <component
          :is="InfoIcon"
          v-if="attribute.sources?.length"
          class="w-3 h-3 text-slate-400 hover:text-slate-200 transition-colors"
        />
      </div>
    </div>

    <div class="mb-3">
      <div class="text-slate-300 text-base font-medium leading-relaxed">
        {{ formattedValue }}
      </div>
      
      <div v-if="attribute.reason" class="text-xs text-slate-500 mt-1 italic">
        {{ attribute.reason }}
      </div>
    </div>

    <div class="flex items-center justify-between text-xs">
      <ConfidenceIndicator :confidence="attribute.confidence" />
      
      <div class="text-slate-500">
        {{ fDateTime(attribute.updated_at) }}
      </div>
    </div>

    <!-- Sources Popover -->
    <AttributeSourcesPopover 
      ref="sourcesPopover"
      :sources="attribute.sources"
      :thread-url="attribute.thread_url"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { fDateTime, fDate, fNumber, fCurrency } from 'quasar-ui-danx';
import type { TeamObjectAttribute } from './team-objects';
import ConfidenceIndicator from './ConfidenceIndicator.vue';
import AttributeSourcesPopover from './AttributeSourcesPopover.vue';
import { FaSolidCircleInfo as InfoIcon } from 'danx-icon';

const props = defineProps<{
  attribute: TeamObjectAttribute;
  format?: 'boolean' | 'shortCurrency' | 'number' | 'date' | 'list' | 'date-time' | 'string';
}>();

const sourcesPopover = ref<InstanceType<typeof AttributeSourcesPopover>>();

const formattedValue = computed(() => {
  if (props.attribute.value === null || props.attribute.value === undefined) {
    return 'No value';
  }

  const value = props.attribute.value;
  
  switch (props.format) {
    case 'boolean':
      return value ? 'Yes' : 'No';
    
    case 'shortCurrency':
    case 'currency':
      return fCurrency(Number(value));
    
    case 'number':
      return fNumber(Number(value));
    
    case 'date':
      return fDate(value as string);
    
    case 'date-time':
      return fDateTime(value as string);
    
    case 'list':
      if (Array.isArray(value)) {
        return value.join(', ');
      }
      return String(value);
    
    default:
      if (Array.isArray(value)) {
        return value.join(', ');
      }
      if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
      }
      return String(value);
  }
});

const onSourcesClick = () => {
  if (props.attribute.sources?.length && sourcesPopover.value) {
    sourcesPopover.value.isOpen = true;
  }
};
</script>