<template>
  <div
    :class="[colors.bgColorLight, colors.borderColorLight]"
    class="w-full rounded-lg p-3 border cursor-pointer transition-all duration-200 group hover:border-opacity-100"
    @click="$emit('select')"
  >
    <!-- Top row: Type Badge, Name, Description (if short), and Date -->
    <div class="flex items-center justify-between">
      <!-- Left: Type Badge and Name with potential inline description -->
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <LabelPillWidget
          :label="objectLabel"
          :class="[colors.bgColor, colors.textColor]"
          size="xs"
        />
        <div class="text-sm font-medium text-slate-100 group-hover:text-white transition-colors">
          {{ relatedObject.name || "Unnamed" }}
        </div>
        <div 
          v-if="relatedObject.description && isDescriptionShort"
          class="text-sm text-slate-300 group-hover:text-slate-200 transition-colors truncate"
        >
          {{ relatedObject.description }}
        </div>
      </div>

      <!-- Right: Date -->
      <div class="flex items-center gap-3 text-xs text-slate-400 flex-shrink-0">
        <span v-if="relatedObject.date">{{ formattedDate }}</span>
      </div>
    </div>

    <!-- Bottom row: Description (if long) -->
    <div 
      v-if="relatedObject.description && !isDescriptionShort"
      class="mt-2 text-sm text-slate-300 group-hover:text-slate-200 transition-colors"
    >
      {{ relatedObject.description }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { LabelPillWidget, fDate } from 'quasar-ui-danx';
import { getTypeColor } from '@/utils/typeColors';
import type { TeamObject } from './team-objects';

const props = defineProps<{
  relatedObject: TeamObject;
}>();

const emit = defineEmits<{
  select: [];
}>();

// Computed properties for cleaner template
const colors = computed(() => getTypeColor(props.relatedObject.type));

const objectLabel = computed(() => 
  `${props.relatedObject.type}: ${props.relatedObject.id}`
);

const formattedDate = computed(() => 
  props.relatedObject.date ? fDate(props.relatedObject.date) : null
);

const isDescriptionShort = computed(() => {
  if (!props.relatedObject.description) return false;
  // Consider description "short" if it's 50 characters or less
  return props.relatedObject.description.length <= 50;
});
</script>