<template>
  <ConfirmDialog
    v-if="isOpen"
    title="Create New Demand"
    content-class="w-[600px]"
    confirm-text="Create Demand"
    cancel-text="Cancel"
    :is-saving="creating"
    :disabled="!isFormValid || isUploading"
    @confirm="handleSubmit"
    @close="handleClose"
  >
    <div class="space-y-6">
      <!-- Title Field -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Title *
        </label>
        <UiInput
          v-model="formData.title"
          placeholder="Enter a descriptive title for your demand..."
          required
          :error="errors.title"
          class="w-full"
        />
      </div>

      <!-- Description Field -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Description
        </label>
        <UiTextarea
          v-model="formData.description"
          placeholder="Provide additional details about your demand..."
          :rows="4"
          :error="errors.description"
          class="w-full"
        />
      </div>

      <!-- File Upload -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Documents
        </label>
        <MultiFileField
          v-model="formData.files"
          placeholder="Upload supporting documents..."
          :error="errors.files"
          class="w-full"
        />
        <p class="text-xs text-slate-500 mt-1">
          Supported formats: PDF, DOC, DOCX, JPG, PNG
        </p>
      </div>

      <!-- Upload Status Warning -->
      <div v-if="isUploading" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
        <div class="flex items-center">
          <FaSolidSpinner class="w-4 h-4 text-amber-600 animate-spin mr-2" />
          <span class="text-sm text-amber-700">
            Files are still uploading... Please wait before saving.
          </span>
        </div>
      </div>
    </div>
  </ConfirmDialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue';
import { FaSolidSpinner } from 'danx-icon';
import { ConfirmDialog, MultiFileField, FlashMessages } from 'quasar-ui-danx';
import { UiInput, UiTextarea } from '../../shared/components';
import { useDemands } from '../composables';
import { DEMAND_STATUS } from '../config';
import type { UiDemand } from '../../shared/types';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  close: [];
}>();

const { createDemand, demands } = useDemands();
const creating = ref(false);
const isUploading = ref(false);

const formData = reactive({
  title: '',
  description: '',
  files: [] as File[],
});

const errors = reactive({
  title: '',
  description: '',
  files: '',
});

const isFormValid = computed(() => {
  return formData.title.trim().length > 0;
});

// Watch for file upload status
watch(() => formData.files, (newFiles) => {
  // Check if any files are still uploading
  isUploading.value = newFiles.some((file: any) => {
    return file instanceof File || (file && file.uploadProgress !== undefined && file.uploadProgress < 100);
  });
}, { deep: true });

const resetForm = () => {
  formData.title = '';
  formData.description = '';
  formData.files = [];
  isUploading.value = false;
  
  // Clear errors
  Object.keys(errors).forEach(key => {
    errors[key as keyof typeof errors] = '';
  });
};

const validateForm = (): boolean => {
  let isValid = true;
  
  // Clear previous errors
  Object.keys(errors).forEach(key => {
    errors[key as keyof typeof errors] = '';
  });
  
  // Validate title
  if (!formData.title.trim()) {
    errors.title = 'Title is required';
    isValid = false;
  } else {
    // Check for duplicate title
    const existingDemand = demands.value.find(demand => 
      demand.title.toLowerCase() === formData.title.trim().toLowerCase()
    );
    if (existingDemand) {
      errors.title = 'A demand with this title already exists';
      isValid = false;
    }
  }
  
  return isValid;
};

const handleSubmit = async () => {
  if (!validateForm()) {
    return;
  }
  
  try {
    creating.value = true;
    
    const result = await createDemand({
      title: formData.title.trim(),
      description: formData.description.trim() || null,
      status: DEMAND_STATUS.DRAFT,
      files: formData.files,
    });
    
    if (result.success) {
      resetForm();
      emit('close');
    } else {
      FlashMessages.error("Failed to create demand" + (result.message ? ": " + result.message : ""));
    }
  } catch (error: any) {
    FlashMessages.error("Failed to create demand" + (error.message ? ": " + error.message : ""));
  } finally {
    creating.value = false;
  }
};

const handleClose = () => {
  // Warn if files are uploading
  if (isUploading.value) {
    if (confirm('Files are still uploading. Are you sure you want to close? Your progress will be lost.')) {
      resetForm();
      emit('close');
    }
  } else {
    resetForm();
    emit('close');
  }
};
</script>