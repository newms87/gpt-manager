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

    <!-- Medical Summaries Section -->
    <DemandMedicalSummaries :demand="demand" />

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
          <div class="space-y-2">
            <div
              v-for="file in outputFiles"
              :key="file.id"
              class="flex items-center space-x-3 p-3 border border-green-200 rounded-lg bg-white hover:bg-green-50 transition-colors"
            >
              <!-- File Type Icon -->
              <div class="flex-shrink-0">
                <component
                  :is="getFileTypeIcon(file)"
                  class="w-5 h-5 text-green-600"
                />
              </div>
              
              <!-- File Information -->
              <div class="flex-grow min-w-0">
                <div class="flex items-center space-x-2">
                  <h4 class="text-sm font-medium text-green-800 truncate">
                    {{ file.filename || file.name || 'Unknown File' }}
                  </h4>
                  <span v-if="file.size" class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded">
                    {{ formatFileSize(file.size) }}
                  </span>
                </div>
                <p v-if="file.created_at || file.updated_at" class="text-xs text-green-600 mt-1">
                  {{ formatFileDate(file) }}
                </p>
              </div>
              
              <!-- Actions -->
              <div class="flex-shrink-0 flex items-center space-x-2">
                <!-- Download Button -->
                <a
                  :href="file.url || file.download_url"
                  target="_blank"
                  class="inline-flex items-center space-x-1 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-100 border border-green-300 rounded-md hover:bg-green-200 hover:border-green-400 transition-colors"
                >
                  <FaSolidDownload class="w-3 h-3" />
                  <span>View/Download</span>
                </a>
                
                <!-- Delete Button -->
                <button
                  @click="handleDeleteOutputFile(file)"
                  :disabled="deletingFileIds.has(file.id)"
                  class="inline-flex items-center justify-center w-8 h-8 text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:border-red-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  title="Delete file"
                >
                  <FaSolidTrash class="w-3 h-3" />
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div v-else class="text-green-600 text-sm flex items-center space-x-2 p-4 bg-green-100 rounded-lg">
          <FaSolidInfo class="w-4 h-4" />
          <span>No output documents generated yet. Complete workflow processing to generate documents.</span>
        </div>
      </div>
    </UiCard>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-if="fileToDelete"
      class="ui-mode"
      title="Delete Output File?"
      color="negative"
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="confirmDelete"
      @close="fileToDelete = null"
    >
      <div class="space-y-4">
        <p class="text-gray-700">
          Are you sure you want to delete this output file?
        </p>
        
        <div class="p-3 bg-gray-50 rounded-md border">
          <div class="flex items-center space-x-2">
            <component
              :is="getFileTypeIcon(fileToDelete)"
              class="w-4 h-4 text-gray-600"
            />
            <span class="font-medium text-gray-900">
              {{ fileToDelete.filename || fileToDelete.name || 'Unknown File' }}
            </span>
          </div>
        </div>
        
        <div class="text-sm text-gray-600 space-y-1">
          <p>This file was generated by workflow processing and will be permanently removed from this demand.</p>
          <p class="font-medium text-red-600">This action cannot be undone.</p>
        </div>
      </div>
    </ConfirmDialog>
  </div>
</template>

<script setup lang="ts">
import { 
  FaSolidFileImport, 
  FaSolidFileExport, 
  FaSolidLock, 
  FaSolidInfo,
  FaSolidDownload,
  FaSolidTrash,
  FaSolidFile,
  FaSolidFilePdf,
  FaSolidFileWord,
  FaSolidFileExcel,
  FaSolidFileImage,
  FaSolidFileLines
} from "danx-icon";
import { MultiFileField, fDateTime, ConfirmDialog, type StoredFile } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";
import DemandMedicalSummaries from "./DemandMedicalSummaries.vue";

const props = defineProps<{
  demand: UiDemand | null;
}>();

const emit = defineEmits<{
  'update:input-files': [files: StoredFile[]];
  'update:output-files': [files: StoredFile[]];
}>();

// Computed properties for file arrays
const inputFiles = computed(() => props.demand?.input_files || []);
const outputFiles = computed(() => props.demand?.output_files || []);

// Loading states
const deletingFileIds = ref<Set<string | number>>(new Set());

// Dialog state
const fileToDelete = ref<StoredFile | null>(null);

// Determine if input files can be edited
const canEditInputFiles = computed(() => {
  return props.demand?.status === DEMAND_STATUS.DRAFT;
});

// Handle input files update
const handleInputFilesUpdate = (files: StoredFile[]) => {
  emit('update:input-files', files);
};

// Handle output file deletion
const handleDeleteOutputFile = (file: StoredFile) => {
  if (!file.id) return;
  
  // Show confirmation dialog
  fileToDelete.value = file;
};

// Confirm file deletion
const confirmDelete = async () => {
  if (!fileToDelete.value?.id) return;
  
  const fileId = fileToDelete.value.id;
  
  try {
    // Add file ID to loading set
    deletingFileIds.value.add(fileId);

    // Remove file from output_files array
    const updatedFiles = outputFiles.value.filter(file => file.id !== fileId);
    
    // Emit update event to parent
    emit('update:output-files', updatedFiles);
  } catch (err) {
    console.error('Error deleting output file:', err);
    // Note: Error handling will be done in parent component
  } finally {
    // Remove file ID from loading set
    deletingFileIds.value.delete(fileId);
    
    // Close dialog
    fileToDelete.value = null;
  }
};

// Helper function to get file type icon based on file extension or mime type
const getFileTypeIcon = (file: StoredFile) => {
  const fileName = (file.filename || file.name)?.toLowerCase() || '';
  const mimeType = (file.mime_type || file.mime)?.toLowerCase() || '';
  
  // PDF files
  if (fileName.endsWith('.pdf') || mimeType.includes('pdf')) {
    return FaSolidFilePdf;
  }
  
  // Word documents
  if (fileName.endsWith('.doc') || fileName.endsWith('.docx') || 
      mimeType.includes('word') || mimeType.includes('document')) {
    return FaSolidFileWord;
  }
  
  // Excel files
  if (fileName.endsWith('.xls') || fileName.endsWith('.xlsx') || 
      mimeType.includes('excel') || mimeType.includes('spreadsheet')) {
    return FaSolidFileExcel;
  }
  
  // Image files
  if (fileName.match(/\.(jpg|jpeg|png|gif|bmp|svg|webp)$/) || 
      mimeType.includes('image')) {
    return FaSolidFileImage;
  }
  
  // Text files
  if (fileName.endsWith('.txt') || fileName.endsWith('.md') || 
      mimeType.includes('text')) {
    return FaSolidFileLines;
  }
  
  // Default file icon
  return FaSolidFile;
};

// Format file size in human readable format
const formatFileSize = (size: number): string => {
  if (!size || size === 0) return '0 B';
  
  const units = ['B', 'KB', 'MB', 'GB'];
  let unitIndex = 0;
  let fileSize = size;
  
  while (fileSize >= 1024 && unitIndex < units.length - 1) {
    fileSize /= 1024;
    unitIndex++;
  }
  
  return `${fileSize.toFixed(1)} ${units[unitIndex]}`;
};

// Format file date
const formatFileDate = (file: StoredFile): string => {
  const date = file.updated_at || file.created_at;
  if (!date) return '';
  
  return `Created ${fDateTime(date, { format: "MMM d, yyyy h:mm a" })}`;
};
</script>