<template>
  <InfoDialog
    v-if="isShowing"
    :title="`Template Variables - ${template?.name || 'Template'}`"
    content-class="w-[85vw] h-[85vh] overflow-hidden bg-slate-950"
    @close="$emit('close')"
  >
    <div class="h-full flex flex-col">
      <!-- Header Section -->
      <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-6 rounded-xl shadow-xl flex-shrink-0">
        <!-- Template Info -->
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <VariablesIcon class="w-6 text-blue-400" />
            <div>
              <h3 class="text-lg font-semibold text-slate-100">Template Variables</h3>
              <p class="text-sm text-slate-400">
                {{ template?.template_url || 'No URL set' }}
              </p>
            </div>
          </div>
          
          <div class="flex items-center gap-2">
            <LabelPillWidget
              :label="`${variableCount} variable${variableCount !== 1 ? 's' : ''}`"
              color="blue"
              size="sm"
            />
            <ActionButton
              :icon="RefreshIcon"
              label="Refresh Variables"
              color="blue"
              size="sm"
              :loading="isRefreshing"
              :disabled="!template?.template_url"
              tooltip="Fetch variables from Google Doc"
              @click="onRefreshVariables"
            />
          </div>
        </div>

        <!-- Save State Indicator -->
        <div v-if="isSaving || hasPendingSaves" class="flex items-center gap-2 text-green-400">
          <QSpinner size="sm" />
          <span class="text-xs">Saving changes...</span>
        </div>
      </div>

      <!-- Variables Content -->
      <div class="flex-grow overflow-hidden flex flex-col mt-6">
        <div class="flex-grow overflow-y-auto px-6 pb-6">
          <!-- Loading State -->
          <div v-if="isRefreshing" class="flex flex-col items-center justify-center py-16">
            <QSpinner size="lg" color="slate-400" />
            <div class="text-slate-400 mt-4">Fetching template variables...</div>
          </div>

          <!-- Empty State -->
          <div
            v-else-if="variableCount === 0"
            class="flex flex-col items-center justify-center py-16 bg-slate-900/30 rounded-xl border-2 border-dashed border-slate-800"
          >
            <VariablesIcon class="w-16 text-slate-600 mb-4" />
            <div class="text-lg text-slate-400 mb-2">No template variables found</div>
            <div class="text-sm text-slate-500 text-center">
              <template v-if="template?.template_url">
                Click "Refresh Variables" to scan your Google Doc for variables like {{variable_name}}
              </template>
              <template v-else>
                Add a Google Docs URL to scan for template variables
              </template>
            </div>
          </div>

          <!-- Variables List -->
          <div v-else class="space-y-4">
            <div
              v-for="(description, variableName) in localVariables"
              :key="variableName"
              class="bg-slate-900/50 rounded-lg p-4 border border-slate-700"
            >
              <div class="flex items-center gap-3 mb-3">
                <div class="bg-blue-500/20 text-blue-300 px-3 py-1 rounded-md font-mono text-sm">
                  {{ formatVariableName(variableName) }}
                </div>
                <div class="text-xs text-slate-500">
                  Variable Name
                </div>
              </div>
              
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-300">
                  Description / Instructions
                </label>
                <MarkdownEditor
                  :model-value="description || ''"
                  placeholder="Enter a description or instructions for this variable..."
                  class="min-h-[100px]"
                  @update:model-value="value => onVariableDescriptionChange(variableName, value)"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </InfoDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ActionButton, InfoDialog, LabelPillWidget } from 'quasar-ui-danx';
import { 
  FaSolidCodeBranch as VariablesIcon,
  FaSolidArrowRotateRight as RefreshIcon
} from 'danx-icon';
import { QSpinner } from 'quasar';
import MarkdownEditor from '@/components/MarkdownEditor/MarkdownEditor';
import { useDemandTemplates } from '../composables/useDemandTemplates';
import type { DemandTemplate } from '../types';

const props = defineProps<{
  template?: DemandTemplate | null;
  isShowing?: boolean;
}>();

const emit = defineEmits<{
  'close': [];
}>();

const { fetchTemplateVariables, mergeTemplateVariables, updateTemplate } = useDemandTemplates();

// State
const isRefreshing = ref(false);
const isSaving = ref(false);
const localVariables = ref<Record<string, string>>({});
const saveTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({});

// Computed
const variableCount = computed(() => Object.keys(localVariables.value).length);

const hasPendingSaves = computed(() => Object.keys(saveTimeouts.value).length > 0);

// Watch for template changes to update local variables
watch(() => props.template?.template_variables, (newVariables) => {
  if (newVariables) {
    localVariables.value = { ...newVariables };
  } else {
    localVariables.value = {};
  }
}, { immediate: true });

// Methods
const onRefreshVariables = async () => {
  if (!props.template?.id || !props.template?.template_url) return;

  isRefreshing.value = true;
  try {
    const fetchedVariables = await fetchTemplateVariables(props.template.id);
    
    // Merge with existing descriptions
    const mergedVariables = mergeTemplateVariables(
      props.template.template_variables || {},
      fetchedVariables || {}
    );
    
    // Update local state
    localVariables.value = mergedVariables;
    
    // Save to backend
    await updateTemplate(props.template, { template_variables: mergedVariables });
  } catch (error) {
    console.error('Failed to refresh template variables:', error);
  } finally {
    isRefreshing.value = false;
  }
};

const onVariableDescriptionChange = (variableName: string, description: string) => {
  // Update local state immediately
  localVariables.value[variableName] = description;
  
  // Clear existing timeout for this variable
  if (saveTimeouts.value[variableName]) {
    clearTimeout(saveTimeouts.value[variableName]);
  }
  
  // Set new timeout for debounced save (1 second)
  saveTimeouts.value[variableName] = setTimeout(async () => {
    if (!props.template) return;
    
    isSaving.value = true;
    try {
      await updateTemplate(props.template, { 
        template_variables: { ...localVariables.value } 
      });
    } catch (error) {
      console.error('Failed to save template variable:', error);
    } finally {
      isSaving.value = false;
      delete saveTimeouts.value[variableName];
    }
  }, 1000);
};

const formatVariableName = (name: string) => {
  return `{{${name}}}`;
};

// Cleanup timeouts on unmount
watch(() => props.isShowing, (showing) => {
  if (!showing) {
    Object.values(saveTimeouts.value).forEach(timeout => clearTimeout(timeout));
    saveTimeouts.value = {};
  }
});
</script>