<template>
  <QDialog
    v-model="isOpen"
    persistent
    maximized
  >
    <QCard class="max-w-4xl mx-auto my-8">
      <QCardSection class="bg-gray-50">
        <div class="text-h6">Select a Template for Writing Demand</div>
      </QCardSection>

      <QCardSection class="q-pt-none">
        <div v-if="isLoading" class="flex justify-center py-8">
          <QSpinner size="lg" color="primary" />
        </div>

        <div v-else-if="templates.length === 0" class="text-center py-8">
          <p class="text-gray-500 mb-4">No active templates available</p>
          <ActionButton
            type="create"
            label="Create Template"
            @click="goToCreateTemplate"
          />
        </div>

        <div v-else>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div
              v-for="template in templates"
              :key="template.id"
              :class="[
                'border rounded-lg p-4 cursor-pointer transition-all',
                selectedTemplate?.id === template.id
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              ]"
              @click="selectedTemplate = template"
            >
              <h3 class="font-semibold mb-2">{{ template.name }}</h3>
              <p class="text-sm text-gray-600 mb-2">{{ template.description || 'No description' }}</p>
              <div class="flex items-center justify-between">
                <span
                  v-if="template.category"
                  class="text-xs bg-gray-100 px-2 py-1 rounded"
                >
                  {{ template.category }}
                </span>
                <a
                  v-if="template.template_url"
                  :href="template.template_url"
                  target="_blank"
                  class="text-xs text-blue-600 hover:text-blue-800"
                  @click.stop
                >
                  Preview
                </a>
              </div>
            </div>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Additional Instructions (Optional)
            </label>
            <TextField
              v-model="additionalInstructions"
              type="textarea"
              :rows="3"
              placeholder="Enter any specific instructions for writing this demand..."
            />
          </div>
        </div>
      </QCardSection>

      <QCardActions align="right" class="bg-gray-50">
        <ActionButton
          type="cancel"
          label="Cancel"
          flat
          @click="handleCancel"
        />
        <ActionButton
          type="submit"
          label="Write Demand"
          :disabled="!selectedTemplate"
          @click="handleConfirm"
        />
      </QCardActions>
    </QCard>
  </QDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { QDialog, QCard, QCardSection, QCardActions, QSpinner } from 'quasar';
import { ActionButton, TextField } from 'quasar-ui-danx';
import { useDemandTemplates } from '../composables/useDemandTemplates';
import type { DemandTemplate } from '../types';

const props = defineProps<{
  modelValue: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  'confirm': [template: DemandTemplate, instructions: string];
}>();

const router = useRouter();
const { loadActiveTemplates } = useDemandTemplates();

const isOpen = ref(props.modelValue);
const templates = ref<DemandTemplate[]>([]);
const selectedTemplate = ref<DemandTemplate | null>(null);
const additionalInstructions = ref('');
const isLoading = ref(false);

watch(() => props.modelValue, (newVal) => {
  isOpen.value = newVal;
  if (newVal) {
    loadTemplates();
  }
});

watch(isOpen, (newVal) => {
  emit('update:modelValue', newVal);
});

const loadTemplates = async () => {
  isLoading.value = true;
  try {
    const response = await loadActiveTemplates();
    templates.value = response.data || response || [];
  } catch (error) {
    console.error('Failed to load templates:', error);
    templates.value = [];
  } finally {
    isLoading.value = false;
  }
};

const handleConfirm = () => {
  if (selectedTemplate.value) {
    emit('confirm', selectedTemplate.value, additionalInstructions.value);
    isOpen.value = false;
    resetForm();
  }
};

const handleCancel = () => {
  isOpen.value = false;
  resetForm();
};

const goToCreateTemplate = () => {
  isOpen.value = false;
  router.push('/ui/templates/new');
};

const resetForm = () => {
  selectedTemplate.value = null;
  additionalInstructions.value = '';
};
</script>