<template>
  <div v-if="object" class="bg-slate-800 rounded-lg shadow-lg border border-slate-700 h-full flex flex-col">
    <div class="flex-1 overflow-y-auto">
      <div class="p-4 space-y-4">
        
        <!-- Compact Header Section -->
        <div class="text-center space-y-2">
          <!-- Type and ID clearly displayed -->
          <div class="flex items-center justify-center gap-2 mb-2">
            <LabelPillWidget :value="object.type" color="purple" size="md" />
            <LabelPillWidget :value="`ID: ${object.id}`" color="slate" size="sm" />
          </div>
          
          <h1 class="text-xl font-bold text-slate-100 leading-tight">
            {{ object.name || 'Unnamed Object' }}
          </h1>
          
          <div class="flex items-center justify-center gap-2 flex-wrap text-sm">
            <span v-if="object.date" class="text-slate-400">
              {{ fDate(object.date) }}
            </span>
            
            <a
              v-if="object.url"
              :href="object.url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-blue-400 hover:text-blue-300 transition-colors"
            >
              <component :is="ExternalLinkIcon" class="w-4 h-4" />
            </a>
          </div>
        </div>

        <!-- Compact Description -->
        <div v-if="object.description" class="bg-slate-750 rounded-lg p-3 border border-slate-600">
          <p class="text-slate-100 text-sm leading-relaxed text-center">
            {{ object.description }}
          </p>
        </div>

        <!-- Key Information Section -->
        <div v-if="attributeCount > 0" class="space-y-3">
          <h2 class="text-lg font-bold text-emerald-400 border-b border-emerald-400/30 pb-1">
            🔑 Key Information
          </h2>
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div
              v-for="(attribute, name) in object.attributes"
              :key="name"
              class="bg-gradient-to-br from-emerald-900/20 to-emerald-800/10 border border-emerald-600/30 rounded-lg p-3 hover:border-emerald-500/50 transition-all duration-200 group relative"
            >
              <!-- Source indicators in top right -->
              <div class="absolute top-2 right-2 flex gap-1">
                <a
                  v-for="source in attribute.sources?.slice(0, 3) || []"
                  :key="source.id"
                  :href="getSourceUrl(source)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-slate-400 hover:text-emerald-300 transition-colors"
                  :title="`Source: ${source.source_type}`"
                >
                  <component :is="getSourceIcon(source.source_type)" class="w-3 h-3" />
                </a>
              </div>

              <!-- Attribute name as header -->
              <h3 class="text-sm font-semibold text-emerald-300 mb-2 pr-8 leading-tight">
                {{ formatAttributeName(name) }}
              </h3>

              <!-- Main value -->
              <div class="mb-2">
                <div class="text-slate-100 text-base font-medium leading-snug">
                  {{ formatAttributeValue(attribute) }}
                </div>
              </div>

              <!-- Confidence and meta info -->
              <div class="flex items-center justify-between text-xs">
                <ConfidenceIndicator :confidence="attribute.confidence" size="sm" />
                <span class="text-slate-500">{{ fDate(attribute.updated_at) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Connected Information Section -->
        <div v-if="relationCount > 0" class="space-y-3">
          <h2 class="text-lg font-bold text-cyan-400 border-b border-cyan-400/30 pb-1">
            🔗 Connected Information
          </h2>
          
          <div class="space-y-3">
            <div
              v-for="(relatedObjects, relationName) in object.relations"
              :key="relationName"
              class="bg-gradient-to-r from-cyan-900/20 to-cyan-800/10 rounded-lg p-3 border border-cyan-600/30"
            >
              <!-- Relation type header -->
              <h3 class="text-sm font-semibold text-cyan-300 mb-2 capitalize">
                {{ formatRelationName(relationName) }}
                <span class="text-xs font-normal text-slate-400 ml-1">
                  ({{ relatedObjects.length }})
                </span>
              </h3>

              <!-- Related object preview cards -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div
                  v-for="relatedObject in relatedObjects.slice(0, 6)"
                  :key="relatedObject.id"
                  class="bg-slate-800/50 rounded-md p-2 border border-slate-600/50 cursor-pointer hover:border-cyan-500/50 hover:bg-slate-800 transition-all duration-200 group"
                  @click="$emit('select-object', relatedObject)"
                >
                  <div class="space-y-1">
                    <div class="text-sm font-medium text-slate-200 group-hover:text-cyan-400 transition-colors truncate">
                      {{ relatedObject.name || 'Unnamed' }}
                    </div>
                    <div class="flex items-center justify-between">
                      <LabelPillWidget :value="relatedObject.type" color="gray" size="xs" />
                      <div v-if="relatedObject.date" class="text-xs text-slate-500">
                        {{ fDate(relatedObject.date) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Show more indicator if there are more items -->
              <div v-if="relatedObjects.length > 6" class="text-xs text-slate-400 text-center mt-2">
                and {{ relatedObjects.length - 6 }} more...
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
  
  <div v-else class="flex items-center justify-center h-64 text-slate-400">
    <div class="text-center space-y-2">
      <div class="text-xl">No object selected</div>
      <div class="text-sm">Choose an object from the list to view its details</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ActionButton, fDate, fDateTime, fNumber, fCurrency, LabelPillWidget } from 'quasar-ui-danx';
import type { TeamObject, TeamObjectAttribute, TeamObjectAttributeSource } from './team-objects';
import ConfidenceIndicator from './ConfidenceIndicator.vue';
import { 
  FaSolidFile as FileIcon,
  FaSolidMessage as MessageIcon,
  FaSolidGlobe as WebIcon,
  FaSolidDatabase as DatabaseIcon,
  FaSolidLink as ExternalLinkIcon
} from 'danx-icon';

const props = defineProps<{
  object?: TeamObject | null;
}>();

const emit = defineEmits<{
  'select-object': [object: TeamObject];
}>();

// Computed values for stats
const attributeCount = computed(() => {
  if (!props.object?.attributes) return 0;
  return Object.keys(props.object.attributes).length;
});

const relationCount = computed(() => {
  if (!props.object?.relations) return 0;
  return Object.values(props.object.relations).reduce((total, relations) => total + relations.length, 0);
});

const sourceCount = computed(() => {
  if (!props.object?.attributes) return 0;
  return Object.values(props.object.attributes).reduce((total, attr) => total + (attr.sources?.length || 0), 0);
});

// Helper functions
const getSourceIcon = (sourceType: string) => {
  switch (sourceType.toLowerCase()) {
    case 'file': return FileIcon;
    case 'message': return MessageIcon;
    case 'web': return WebIcon;
    case 'database': return DatabaseIcon;
    default: return FileIcon;
  }
};

const formatAttributeName = (name: string): string => {
  // Convert snake_case and camelCase to human readable
  return name
    .replace(/[_-]/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, l => l.toUpperCase());
};

const formatRelationName = (name: string): string => {
  // Convert snake_case to human readable and make it more natural
  return name
    .replace(/_/g, ' ')
    .replace(/\b\w/g, l => l.toUpperCase());
};

const formatAttributeValue = (attribute: TeamObjectAttribute): string => {
  if (attribute.value === null || attribute.value === undefined) {
    return 'No value';
  }

  const value = attribute.value;
  
  // Try to detect value type and format accordingly
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }
  
  if (typeof value === 'number') {
    // Check if it looks like currency
    if (attribute.name.toLowerCase().includes('price') || 
        attribute.name.toLowerCase().includes('cost') || 
        attribute.name.toLowerCase().includes('amount')) {
      return fCurrency(value);
    }
    return fNumber(value);
  }
  
  if (typeof value === 'string') {
    // Check if it looks like a date
    const dateRegex = /^\d{4}-\d{2}-\d{2}/;
    if (dateRegex.test(value)) {
      return fDate(value);
    }
    
    // Limit very long strings
    if (value.length > 200) {
      return value.substring(0, 200) + '...';
    }
  }
  
  if (Array.isArray(value)) {
    return value.slice(0, 5).join(', ') + (value.length > 5 ? '...' : '');
  }
  
  if (typeof value === 'object') {
    return JSON.stringify(value, null, 2);
  }
  
  return String(value);
};

const getSourceUrl = (source: TeamObjectAttributeSource): string => {
  if (source.sourceFile?.url) {
    return source.sourceFile.url;
  } else if (source.thread_url) {
    return source.thread_url;
  }
  return '#';
};

const openUrl = (url: string) => {
  window.open(url, '_blank');
};
</script>