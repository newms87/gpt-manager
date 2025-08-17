<template>
  <div class="space-y-6">
    <!-- Input Documents Section -->
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

    <!-- Output Documents Section -->
    <UiCard class="border-green-200 bg-green-50">
      <template #header>
        <div class="flex items-center space-x-2">
          <FaSolidFileExport class="w-5 h-5 text-green-600" />
          <h3 class="text-lg font-semibold text-green-800">
            Output Documents
          </h3>
          <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full">
            {{ outputFiles.length }}
          </span>
        </div>
      </template>

      <div class="space-y-3">
        <p class="text-green-700 text-sm">
          Generated documents from workflow processing.
        </p>
        
        <div v-if="outputFiles.length > 0">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="file in outputFiles"
              :key="file.id"
              class="border border-green-200 rounded-lg p-3 bg-white hover:shadow-md transition-shadow"
            >
              <FilePreview
                :file="file"
                downloadable
                :removable="false"
                class="w-full"
              />
            </div>
          </div>
        </div>
        
        <div v-else class="text-green-600 text-sm flex items-center space-x-2 p-4 bg-green-100 rounded-lg">
          <FaSolidInfo class="w-4 h-4" />
          <span>No output documents generated yet. Complete workflow processing to generate documents.</span>
        </div>
      </div>
    </UiCard>
  </div>
</template>

<script setup lang="ts">
import { FaSolidFileImport, FaSolidFileExport, FaSolidLock, FaSolidInfo } from "danx-icon";
import { MultiFileField, FilePreview, type StoredFile } from "quasar-ui-danx";
import { computed } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";

const props = defineProps<{
  demand: UiDemand | null;
}>();

const emit = defineEmits<{
  'update:input-files': [files: StoredFile[]];
}>();

// Computed properties for file arrays
const inputFiles = computed(() => props.demand?.input_files || []);
const outputFiles = computed(() => props.demand?.output_files || []);

// Determine if input files can be edited
const canEditInputFiles = computed(() => {
  return props.demand?.status === DEMAND_STATUS.DRAFT;
});

// Handle input files update
const handleInputFilesUpdate = (files: StoredFile[]) => {
  emit('update:input-files', files);
};
</script>