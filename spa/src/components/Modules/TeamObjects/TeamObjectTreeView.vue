<template>
  <div class="bg-slate-800 rounded-lg border border-slate-700 h-full flex flex-col">
    <!-- Compact Header -->
    <div class="p-3 border-b border-slate-700">
      <h3 class="text-base font-semibold text-slate-200">Object Tree</h3>
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
        <TreeNodeComponent
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
import { computed, ref, defineComponent, h, watch } from 'vue';
import type { TeamObject, TeamObjectAttribute } from './team-objects';
import { 
  FaSolidChevronRight as ChevronRightIcon,
  FaSolidUsers as UsersIcon,
  FaSolidBuilding as BuildingIcon,
  FaSolidGear as GearIcon,
  FaSolidLink as LinkIcon,
  FaSolidFolder as FolderIcon,
  FaSolidTag as TagIcon
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

// Helper functions
const getTypeColor = (type: string) => {
  const hash = type.split('').reduce((a, b) => {
    a = ((a << 5) - a) + b.charCodeAt(0);
    return a & a;
  }, 0);
  
  const colors = [
    'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-red-500',
    'bg-yellow-500', 'bg-indigo-500', 'bg-pink-500', 'bg-teal-500'
  ];
  
  return colors[Math.abs(hash) % colors.length];
};

const getRelationshipIcon = (relationName: string) => {
  const name = relationName.toLowerCase();
  
  if (name.includes('user') || name.includes('person') || name.includes('people') || name.includes('provider')) {
    return UsersIcon;
  } else if (name.includes('building') || name.includes('facility') || name.includes('location') || name.includes('place')) {
    return BuildingIcon;
  } else if (name.includes('config') || name.includes('setting') || name.includes('system')) {
    return GearIcon;
  } else if (name.includes('folder') || name.includes('directory') || name.includes('container')) {
    return FolderIcon;
  } else if (name.includes('tag') || name.includes('label') || name.includes('category')) {
    return TagIcon;
  } else {
    return LinkIcon;
  }
};

const getConfidenceCounts = (attributes?: Record<string, TeamObjectAttribute>) => {
  if (!attributes) return {};
  
  return Object.values(attributes).reduce((acc: any, attr: any) => {
    const conf = attr.confidence?.toLowerCase() || 'none';
    acc[conf] = (acc[conf] || 0) + 1;
    return acc;
  }, {});
};

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

// TreeNode component definition using render function
const TreeNodeComponent = defineComponent({
  name: 'TreeNodeComponent',
  props: {
    object: {
      type: Object as () => TeamObject,
      required: true
    },
    selectedObject: {
      type: Object as () => TeamObject | null | undefined,
      default: null
    },
    expandedNodes: {
      type: Object as () => Set<number>,
      required: true
    },
    level: {
      type: Number,
      required: true
    }
  },
  emits: ['select', 'toggle'],
  setup(props, { emit }) {
    const isExpanded = computed(() => props.expandedNodes.has(props.object.id));
    const isSelected = computed(() => props.selectedObject?.id === props.object.id);
    const hasChildren = computed(() => 
      Object.values(props.object.relations || {}).some(relations => relations.length > 0)
    );
    const childrenCount = computed(() => 
      Object.values(props.object.relations || {}).reduce((total, relations) => total + relations.length, 0)
    );
    const confidenceCounts = computed(() => getConfidenceCounts(props.object.attributes));

    const handleSelect = () => {
      emit('select', props.object);
    };

    const handleToggle = (e: Event) => {
      e.stopPropagation();
      emit('toggle', props.object.id);
    };

    return {
      isExpanded,
      isSelected,
      hasChildren,
      childrenCount,
      confidenceCounts,
      handleSelect,
      handleToggle,
      getTypeColor,
      getRelationshipIcon,
      ChevronRightIcon
    };
  },
  render() {
    const { 
      isExpanded, 
      isSelected, 
      hasChildren, 
      childrenCount, 
      confidenceCounts, 
      handleSelect, 
      handleToggle, 
      getTypeColor,
      getRelationshipIcon,
      ChevronRightIcon 
    } = this;

    const { object, selectedObject, expandedNodes, level } = this.$props;

    return h('div', [
      // Main node container
      h('div', {
        class: [
          'flex items-center gap-2 p-1.5 rounded cursor-pointer transition-all duration-200',
          isSelected 
            ? 'bg-blue-600/30 border border-blue-500/50 text-blue-100' 
            : 'hover:bg-slate-700 text-slate-300',
          level > 0 ? 'ml-4' : ''
        ],
        onClick: handleSelect
      }, [
        // Expand/collapse button or spacer
        hasChildren 
          ? h('button', {
              class: 'flex items-center justify-center w-4 h-4 rounded hover:bg-slate-600 transition-colors',
              onClick: handleToggle
            }, [
              h(ChevronRightIcon, {
                class: [
                  'w-3 h-3 text-slate-400 transition-transform',
                  isExpanded ? 'transform rotate-90' : ''
                ]
              })
            ])
          : h('div', { class: 'w-4' }),
        
        // Content area
        h('div', { class: 'flex items-center gap-2 flex-1 min-w-0' }, [
          // Type indicator
          h('div', {
            class: ['w-3 h-3 rounded-full flex-shrink-0', getTypeColor(object.type)]
          }),
          
          // Text content
          h('div', { class: 'flex-1 min-w-0' }, [
            h('div', { class: 'text-sm font-medium truncate' }, object.name || 'Unnamed'),
            h('div', { class: 'text-xs text-slate-500' }, [
              object.type,
              childrenCount > 0 ? ` • ${childrenCount}` : ''
            ])
          ]),
          
          // Confidence indicators
          h('div', { class: 'flex items-center gap-1' }, [
            confidenceCounts.high > 0 && h('div', {
              class: 'w-2 h-2 rounded-full bg-green-500',
              title: `${confidenceCounts.high} high confidence`
            }),
            confidenceCounts.medium > 0 && h('div', {
              class: 'w-2 h-2 rounded-full bg-amber-500',
              title: `${confidenceCounts.medium} medium confidence`
            }),
            confidenceCounts.low > 0 && h('div', {
              class: 'w-2 h-2 rounded-full bg-red-500',
              title: `${confidenceCounts.low} low confidence`
            })
          ])
        ])
      ]),
      
      // Children container with relationship grouping
      isExpanded && hasChildren && h('div', {
        class: 'ml-4 border-l border-slate-600 pl-2 mt-2 space-y-3'
      }, Object.entries(object.relations || {}).map(([relationName, relatedObjects]) => 
        relatedObjects.length > 0 ? h('div', { key: relationName }, [
          // Relationship header
          h('div', {
            class: 'flex items-center gap-2 px-2 py-1 text-xs font-medium italic text-slate-400 border-b border-slate-700/50 mb-2'
          }, [
            // Relationship icon based on name
            h(getRelationshipIcon(relationName), {
              class: 'w-3 h-3 text-slate-400 flex-shrink-0'
            }),
            h('span', { class: 'capitalize' }, relationName.replace(/_/g, ' ')),
            h('span', { class: 'text-slate-500' }, `(${relatedObjects.length})`)
          ]),
          // Related objects under this relationship
          h('div', { class: 'space-y-1' }, 
            relatedObjects.map((relatedObject: TeamObject) =>
              h(TreeNodeComponent, {
                key: relatedObject.id,
                object: relatedObject,
                selectedObject: selectedObject,
                expandedNodes: expandedNodes,
                level: level + 1,
                onSelect: (obj: TeamObject) => this.$emit('select', obj),
                onToggle: (objId: number) => this.$emit('toggle', objId)
              })
            )
          )
        ]) : null
      ).filter(Boolean))
    ]);
  }
});

// Expose methods for parent component
defineExpose({
  expandPathToObject,
  expandedNodes
});
</script>