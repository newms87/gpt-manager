<template>
  <QPopupProxy
    v-model="isOpen"
    anchor="bottom left"
    self="top left"
    class="bg-slate-800 rounded-lg shadow-lg border border-slate-700"
    @show="onShow"
  >
    <div class="p-4 max-w-md">
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-slate-200">
          Attribute Sources
        </h4>
        <QBtn
          flat
          round
          dense
          :icon="CloseIcon"
          class="text-slate-400 hover:text-slate-200"
          @click="isOpen = false"
        />
      </div>

      <div v-if="!sources?.length" class="text-slate-400 text-sm">
        No sources available
      </div>

      <div v-else class="space-y-3">
        <div 
          v-for="source in sources" 
          :key="source.id"
          class="border border-slate-600 rounded-lg p-3 bg-slate-750"
        >
          <div class="flex items-start justify-between mb-2">
            <div class="flex items-center gap-2">
              <component 
                :is="getSourceIcon(source.source_type)" 
                class="w-4 h-4 text-slate-400"
              />
              <span class="text-xs font-medium text-slate-300 capitalize">
                {{ source.source_type }}
              </span>
            </div>
            
            <ActionButton
              v-if="source.sourceFile"
              type="view"
              size="xs"
              color="slate"
              tooltip="Download file"
              @click="downloadFile(source.sourceFile)"
            />
            
            <ActionButton
              v-else-if="source.sourceMessage && source.thread_url"
              type="view"
              size="xs"
              color="slate"
              tooltip="View thread"
              @click="openThread(source.thread_url)"
            />
          </div>

          <div v-if="source.explanation" class="text-sm text-slate-300 mb-2">
            {{ source.explanation }}
          </div>

          <div class="text-xs text-slate-400">
            {{ fDateTime(source.created_at) }}
          </div>
        </div>
      </div>
    </div>
  </QPopupProxy>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { QPopupProxy, QBtn } from 'quasar';
import { ActionButton, fDateTime } from 'quasar-ui-danx';
import type { TeamObjectAttributeSource } from './team-objects';
import { 
  FaSolidFile as FileIcon,
  FaSolidMessage as MessageIcon,
  FaSolidX as CloseIcon
} from 'danx-icon';

const props = defineProps<{
  sources?: TeamObjectAttributeSource[];
  threadUrl?: string;
}>();

const emit = defineEmits<{
  'show': [];
}>();

const isOpen = ref(false);

const getSourceIcon = (sourceType: string) => {
  switch (sourceType.toLowerCase()) {
    case 'file':
      return FileIcon;
    case 'message':
      return MessageIcon;
    default:
      return FileIcon;
  }
};

const downloadFile = (file: any) => {
  if (file?.url) {
    window.open(file.url, '_blank');
  }
};

const openThread = (threadUrl?: string) => {
  if (threadUrl) {
    window.open(threadUrl, '_blank');
  }
};

const onShow = () => {
  emit('show');
};

// Expose the isOpen state for parent component control
defineExpose({
  isOpen
});
</script>