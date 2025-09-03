<template>
  <div class="bg-slate-800 rounded-lg border border-slate-700 h-full flex flex-col">
    <!-- Compact Header -->
    <div class="p-3 border-b border-slate-700">
      <h3 class="text-base font-semibold text-slate-200">{{ treeTitle }}</h3>
    </div>
    
    <!-- Breadcrumb Navigation -->
    <div v-if="breadcrumbs.length > 1" class="p-2 border-b border-slate-700 bg-slate-750">
      <div class="flex items-center gap-1 text-xs">
        <button
          v-for="(crumb, index) in breadcrumbs"
          :key="crumb.id"
          class="text-slate-300 hover:text-slate-100 transition-colors"
          :class="{ 'text-slate-500': index === breadcrumbs.length - 1 }"
          @click="navigateToBreadcrumb(crumb, index)"
        >
          {{ crumb.name || 'Root' }}
          <component
            v-if="index < breadcrumbs.length - 1"
            :is="ChevronRightIcon"
            class="w-2 h-2 inline ml-1"
          />
        </button>
      </div>
    </div>

    <!-- Tree Content with proper scrolling -->
    <div class="flex-1 overflow-y-auto p-3">
      <div v-if="!rootObjects.length" class="text-center text-slate-400 py-8">
        No objects available
      </div>
      
      <div v-else class="space-y-1">
        <TreeNode
          v-for="object in rootObjects"
          :key="object.id"
          :object="object"
          :selected-object="selectedObject"
          :expanded-nodes="expandedNodes"
          :level="0"
          @select="onSelectObject"
          @toggle="onToggleNode"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { TeamObject } from './team-objects';
import TreeNode from './TreeNode.vue';
import { 
  FaSolidChevronRight as ChevronRightIcon
} from 'danx-icon';

const props = defineProps<{
  objects: TeamObject[];
  selectedObject?: TeamObject | null;
}>();

const emit = defineEmits<{
  'select-object': [object: TeamObject];
  'navigate': [object: TeamObject];
}>();

const expandedNodes = ref(new Set<number>());
const breadcrumbs = ref<TeamObject[]>([]);

const rootObjects = computed(() => props.objects);

// Dynamic tree title based on root object types
const treeTitle = computed(() => {
  if (!rootObjects.value.length) return 'Object Tree';
  
  // Get the most common type among root objects
  const typeCounts = rootObjects.value.reduce((acc, obj) => {
    acc[obj.type] = (acc[obj.type] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);
  
  const mostCommonType = Object.keys(typeCounts).reduce((a, b) => 
    typeCounts[a] > typeCounts[b] ? a : b
  );
  
  return `${mostCommonType} List`;
});

// Watch for changes to selected object and auto-expand path to it
watch(() => props.selectedObject, (newSelected) => {
  if (newSelected) {
    // Clear all expanded nodes first (collapse all)
    expandedNodes.value.clear();
    
    // Find and expand path to selected object
    expandPathToObject(newSelected);
    
    // Also expand the selected object itself to show its children
    expandedNodes.value.add(newSelected.id);
  }
});


const onSelectObject = (object: TeamObject) => {
  emit('select-object', object);
};

const onToggleNode = (objectId: number) => {
  if (expandedNodes.value.has(objectId)) {
    expandedNodes.value.delete(objectId);
  } else {
    expandedNodes.value.add(objectId);
  }
};

const navigateToBreadcrumb = (crumb: TeamObject, index: number) => {
  breadcrumbs.value = breadcrumbs.value.slice(0, index + 1);
  emit('navigate', crumb);
};

// Auto-expand nodes when an object is selected
const expandPathToObject = (targetObject: TeamObject) => {
  const findAndExpandPath = (objects: TeamObject[], target: TeamObject, path: number[] = []): boolean => {
    for (const obj of objects) {
      const currentPath = [...path, obj.id];
      
      if (obj.id === target.id) {
        // Found the target, expand all nodes in the path
        path.forEach(id => expandedNodes.value.add(id));
        return true;
      }
      
      // Search in all related objects
      const allRelated = Object.values(obj.relations || {}).flat();
      if (findAndExpandPath(allRelated, target, currentPath)) {
        // Found in children, expand current node too
        expandedNodes.value.add(obj.id);
        return true;
      }
    }
    return false;
  };
  
  findAndExpandPath(rootObjects.value, targetObject);
};


// Expose methods for parent component
defineExpose({
  expandPathToObject,
  expandedNodes
});
</script>