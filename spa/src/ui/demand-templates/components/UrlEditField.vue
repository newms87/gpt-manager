<template>
  <div class="url-edit-field">
    <div v-if="!isEditing" class="flex items-center gap-2">
      <span 
        class="text-slate-300 text-sm flex-1"
        :class="{ 'text-slate-500 italic': !modelValue }"
      >
        {{ modelValue || 'No URL set' }}
      </span>
      <ActionButton
        type="edit"
        tooltip="Edit URL"
        size="sm"
        @click="startEditing"
      />
    </div>
    
    <div v-else class="flex items-center gap-2">
      <TextField
        v-model="editValue"
        placeholder="Enter Google Docs URL"
        class="flex-1"
        :class="{ 'border-red-500': hasError }"
        @keyup.enter="saveUrl"
        @keyup.escape="cancelEdit"
      />
      <ActionButton
        :loading="isSaving"
        tooltip="Save URL"
        size="sm"
        @click="saveUrl"
      >
        <CheckIcon class="w-4" />
      </ActionButton>
      <ActionButton
        tooltip="Cancel"
        size="sm"
        @click="cancelEdit"
      >
        <CloseIcon class="w-4" />
      </ActionButton>
    </div>
    
    <div v-if="hasError && errorMessage" class="text-red-400 text-xs mt-1">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { ActionButton, TextField } from 'quasar-ui-danx';
import { FaSolidCheck as CheckIcon, FaSolidX as CloseIcon } from 'danx-icon';

const props = withDefaults(defineProps<{
  modelValue?: string;
  loading?: boolean;
}>(), {
  modelValue: '',
  loading: false
});

const emit = defineEmits<{
  'url-saved': [url: string];
}>();

// State
const isEditing = ref(false);
const editValue = ref('');
const isSaving = ref(false);
const hasError = ref(false);
const errorMessage = ref('');

// Methods
const startEditing = async () => {
  isEditing.value = true;
  editValue.value = props.modelValue || '';
  hasError.value = false;
  errorMessage.value = '';
  
  await nextTick();
  // Focus the input field
  const input = document.querySelector('.url-edit-field input') as HTMLInputElement;
  input?.focus();
};

const cancelEdit = () => {
  isEditing.value = false;
  editValue.value = '';
  hasError.value = false;
  errorMessage.value = '';
};

const validateUrl = (url: string): { isValid: boolean; error?: string } => {
  if (!url.trim()) {
    return { isValid: false, error: 'URL is required' };
  }
  
  if (!url.includes('docs.google.com')) {
    return { isValid: false, error: 'Must be a Google Docs URL' };
  }
  
  try {
    new URL(url);
    return { isValid: true };
  } catch {
    return { isValid: false, error: 'Invalid URL format' };
  }
};

const saveUrl = async () => {
  const validation = validateUrl(editValue.value);
  
  if (!validation.isValid) {
    hasError.value = true;
    errorMessage.value = validation.error || 'Invalid URL';
    return;
  }
  
  try {
    isSaving.value = true;
    hasError.value = false;
    errorMessage.value = '';
    
    emit('url-saved', editValue.value.trim());
    
    isEditing.value = false;
    editValue.value = '';
  } catch (error) {
    hasError.value = true;
    errorMessage.value = 'Failed to save URL';
    console.error('Error saving URL:', error);
  } finally {
    isSaving.value = false;
  }
};
</script>