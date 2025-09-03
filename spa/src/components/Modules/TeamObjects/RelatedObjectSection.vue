<template>
  <div class="space-y-3">
    <!-- Section Header -->
    <h2 
      class="text-lg font-bold border-b pb-1"
      :class="[sectionColors.textColor, sectionColors.borderColor + '/30']"
    >
      🔗 {{ formattedRelationName }}
      <span class="text-xs font-normal text-slate-400 ml-1">
        ({{ relatedObjects.length }})
      </span>
    </h2>

    <!-- Related Objects List -->
    <div class="space-y-2">
      <RelatedObjectRow
        v-for="relatedObject in relatedObjects"
        :key="relatedObject.id"
        :related-object="relatedObject"
        @select="$emit('select-object', relatedObject)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { getTypeColor } from '@/utils/typeColors';
import type { TeamObject } from './team-objects';
import RelatedObjectRow from './RelatedObjectRow.vue';

const props = defineProps<{
  relationName: string;
  relatedObjects: TeamObject[];
}>();

const emit = defineEmits<{
  'select-object': [object: TeamObject];
}>();

// Format the relation name for display
const formattedRelationName = computed(() => {
  return props.relationName
    .replace(/_/g, ' ')
    .replace(/\b\w/g, l => l.toUpperCase());
});

// Get section colors based on most common type in the relationship
const sectionColors = computed(() => {
  if (props.relatedObjects.length === 0) {
    return getTypeColor('default');
  }

  // Find the most common type among related objects
  const typeCounts = props.relatedObjects.reduce((acc, obj) => {
    acc[obj.type] = (acc[obj.type] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);

  const mostCommonType = Object.keys(typeCounts).reduce((a, b) =>
    typeCounts[a] > typeCounts[b] ? a : b
  );

  return getTypeColor(mostCommonType);
});
</script>