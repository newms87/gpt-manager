<template>
  <div class="demand-form">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Title Field -->
      <UiInput
        v-model="formData.title"
        label="Demand Title"
        placeholder="Enter a descriptive title for your demand..."
        required
        :error="errors.title"
        :prefix-icon="FaSolidFile"
      />

      <!-- Description Field -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Description
          <span class="text-slate-500 font-normal">(Optional)</span>
        </label>
        <textarea
          v-model="formData.description"
          placeholder="Provide additional details about your demand..."
          rows="4"
          class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors duration-200 placeholder:text-slate-400"
          :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500/20': errors.description }"
        />
        <p v-if="errors.description" class="mt-1 text-sm text-red-600">
          {{ errors.description }}
        </p>
      </div>

      <!-- Files Upload -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Documents
          <span class="text-slate-500 font-normal">(Optional)</span>
        </label>
        <MultiFileField
          v-model="formData.files"
          :width="70"
          :height="60"
          add-icon-class="w-5"
          file-preview-class="rounded-lg"
          file-preview-btn-size="xs"
          placeholder="Upload documents..."
        />
      </div>

      <!-- Form Actions -->
      <div class="flex items-center justify-between pt-4 border-t border-slate-200">
        <ActionButton
          type="cancel"
          @click="$emit('cancel')"
        />

        <div class="flex space-x-3">
          <ActionButton
            type="draft" 
            :disabled="!isValid || saving"
            label="Save as Draft"
            @click="handleSaveDraft"
          />

          <ActionButton
            type="save"
            :loading="saving"
            :disabled="!isValid"
            :label="mode === 'create' ? 'Create Demand' : 'Update Demand'"
            @click="handleSubmit"
          />
        </div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { FaSolidFile } from 'danx-icon';
import { MultiFileField, ActionButton } from 'quasar-ui-danx';
import { UiInput } from '../../shared/components';
import { useDemandForm } from '../composables';
import type { UiDemand } from '../../shared/types';

const props = withDefaults(defineProps<{
  mode?: 'create' | 'edit';
  initialData?: Partial<UiDemand>;
}>(), {
  mode: 'create',
});

defineEmits<{
  submit: [data: { title: string; description: string; files?: any[] }];
  'save-draft': [data: { title: string; description: string; files?: any[] }];
  cancel: [];
}>();

const { formData, errors, isValid, validate, setData } = useDemandForm(props.initialData);

const saving = ref(false);

// Watch for changes in initial data
watch(() => props.initialData, (newData) => {
  if (newData) {
    setData(newData);
  }
}, { immediate: true });

const handleSubmit = async () => {
  if (!validate()) return;

  try {
    saving.value = true;
    emit('submit', {
      title: formData.value.title,
      description: formData.value.description,
      files: formData.value.files,
    });
  } finally {
    saving.value = false;
  }
};

const handleSaveDraft = async () => {
  if (!validate()) return;

  try {
    saving.value = true;
    emit('save-draft', {
      title: formData.value.title,
      description: formData.value.description,
      files: formData.value.files,
    });
  } finally {
    saving.value = false;
  }
};
</script>