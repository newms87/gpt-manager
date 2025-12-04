<template>
  <div class="space-y-6">
    <!-- Input Documents Section (always shown) -->
    <UiCard class="border-blue-200 bg-blue-50">
      <template #header>
        <div class="flex items-center space-x-2">
          <FaSolidFileImport class="w-5 h-5 text-blue-600" />
          <h3 class="text-lg font-semibold text-blue-800">
            Input Documents
          </h3>
          <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full">
            {{ inputFiles.length }}
          </span>
        </div>
      </template>

      <div class="space-y-3">
        <p class="text-blue-700 text-sm">
          Upload source documents for analysis and processing.
        </p>

        <MultiFileField
          :model-value="inputFiles"
          :readonly="!canEditInputFiles"
          :disabled="!canEditInputFiles"
          :width="70"
          :height="60"
          add-icon-class="w-5"
          show-transcodes
          file-preview-class="rounded-lg border-blue-200"
          file-preview-btn-size="xs"
          @update:model-value="handleInputFilesUpdate"
        />

        <div v-if="!canEditInputFiles" class="text-blue-600 text-xs flex items-center space-x-1">
          <FaSolidLock class="w-3 h-3" />
          <span>Input files can only be edited when demand is in draft status</span>
        </div>
      </div>
    </UiCard>

    <!-- Dynamic Artifact Sections from workflow config -->
    <WorkflowArtifactSection
      v-for="section in demand?.artifact_sections"
      :key="section.workflow_key"
      :section="section"
    />
  </div>
</template>

<script setup lang="ts">
import { FaSolidFileImport, FaSolidLock } from "danx-icon";
import { MultiFileField, type StoredFile } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";
import WorkflowArtifactSection from "./WorkflowArtifactSection.vue";
import { useStoredFileUpdates } from "@/composables/useStoredFileUpdates";

const props = defineProps<{
  demand: UiDemand | null;
}>();

const emit = defineEmits<{
  'update:input-files': [files: StoredFile[]];
}>();

// Computed properties for file arrays
const inputFiles = computed(() => props.demand?.input_files || []);

// Determine if input files can be edited
const canEditInputFiles = computed(() => {
  return props.demand?.status === DEMAND_STATUS.DRAFT;
});

// Handle input files update
const handleInputFilesUpdate = (files: StoredFile[]) => {
  emit('update:input-files', files);
};

// Subscribe to file updates for real-time transcoding progress
const { subscribeToFileUpdates, unsubscribeFromFileUpdates } = useStoredFileUpdates();

// Track previously subscribed file IDs
const subscribedFileIds = ref<Set<string>>(new Set());

// Subscribe/unsubscribe to input files when they change
watch(() => inputFiles.value, (newFiles, oldFiles) => {
  const newFileIds = new Set(newFiles?.map(f => f?.id).filter(Boolean) || []);

  // Unsubscribe from files that were removed
  subscribedFileIds.value.forEach(fileId => {
    if (!newFileIds.has(fileId)) {
      const oldFile = oldFiles?.find(f => f?.id === fileId);
      if (oldFile) {
        unsubscribeFromFileUpdates(oldFile);
      }
      subscribedFileIds.value.delete(fileId);
    }
  });

  // Subscribe to new files
  newFiles?.forEach(file => {
    if (file?.id && !subscribedFileIds.value.has(file.id)) {
      subscribeToFileUpdates(file);
      subscribedFileIds.value.add(file.id);
    }
  });
}, { immediate: true, deep: true });
</script>