<template>
    <div class="instruction-template-card bg-white rounded-lg shadow-md border border-slate-200 p-6 hover:shadow-lg transition-all duration-200 w-full">
        <!-- Header with Name and Actions -->
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <EditableDiv
                    :model-value="template.name"
                    class="text-xl font-semibold text-slate-800 rounded-sm px-2 py-1 -mx-2 -my-1"
                    placeholder="Template Name"
                    @update:model-value="updateTemplate({ name: $event })"
                />
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 ml-4">
                <!-- Edit Content Toggle -->
                <ShowHideButton
                    v-model="isEditingContent"
                    :show-icon="EditIcon"
                    :hide-icon="EditIcon"
                    tooltip="Edit Content"
                    size="sm"
                    color="blue"
                    class="bg-blue-100"
                />

                <!-- Delete Button -->
                <ActionButton
                    type="trash"
                    tooltip="Delete Template"
                    size="sm"
                    color="red"
                    @click="$emit('delete', template)"
                />
            </div>
        </div>

        <!-- Description -->
        <div class="mb-4">
            <EditableDiv
                :model-value="template.description || ''"
                class="text-slate-600"
                placeholder="Enter description..."
                @update:model-value="updateTemplate({ description: $event })"
            />
        </div>

        <!-- Content Preview/Editor -->
        <div v-if="template.content && !isEditingContent" class="mb-4">
            <div class="text-sm font-medium text-slate-700 mb-2">Content Preview:</div>
            <div class="bg-slate-50 border border-slate-200 rounded-md p-3 text-sm text-slate-700 max-h-32 overflow-y-auto">
                {{ template.content.substring(0, 200) }}{{ template.content.length > 200 ? "..." : "" }}
            </div>
        </div>

        <!-- Content Editor -->
        <div v-if="isEditingContent" class="mb-4">
            <div class="text-sm font-medium text-slate-700 mb-2">Content:</div>
            <MarkdownEditor
                :model-value="template.content || ''"
                placeholder="Enter your instructions..."
                @update:model-value="content => debouncedUpdateAction.trigger(template, { content })"
            />
            <SaveStateIndicator
                class="mt-1"
                :saving="debouncedUpdateAction.isApplying"
                :saved-at="template.updated_at"
            />
        </div>

        <!-- Files -->
        <div v-if="template.files && template.files.length > 0" class="mb-4">
            <div class="text-sm font-medium text-slate-700 mb-2">Attachments:</div>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="file in template.files"
                    :key="file.id"
                    class="flex items-center gap-2 bg-slate-100 rounded px-2 py-1 text-xs"
                >
                    <FaSolidFile class="w-3 h-3 text-slate-500" />
                    <span class="text-slate-700">{{ file.name }}</span>
                </div>
            </div>
        </div>

        <!-- Metadata -->
        <div class="flex items-center justify-between text-xs text-slate-500 pt-4 border-t border-slate-200">
            <div>
                Created {{ formatDate(template.created_at) }}
            </div>
            <div v-if="template.user" class="font-medium text-slate-600">
                by {{ template.user.name }}
            </div>
        </div>

        <!-- Loading Overlay -->
        <div
            v-if="isUpdating"
            class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg"
        >
            <QSpinner color="blue" size="md" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/config";
import type { WorkflowInput } from "@/types";
import { FaSolidFile, FaSolidPencil as EditIcon } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, EditableDiv, fDate, MarkdownEditor, SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{
    template: WorkflowInput;
}>();

const emit = defineEmits<{
    "update": [template: WorkflowInput, data: Partial<WorkflowInput>];
    "delete": [template: WorkflowInput];
}>();

// Actions
const updateAction = dxWorkflowInput.getAction("update");
const debouncedUpdateAction = dxWorkflowInput.getAction("update", { debounce: 500 });

// State
const isUpdating = ref(false);
const isEditingContent = ref(false);

const updateTemplate = (data: Partial<WorkflowInput>) => {
    emit("update", props.template, data);
};

const formatDate = (dateString: string) => {
    return fDate(dateString);
};
</script>

<style scoped>
.instruction-template-card {
    position: relative;
}

.instruction-template-card:hover .q-btn {
    opacity: 1;
}

.q-btn {
    opacity: 0.7;
    transition: opacity 0.2s;
}
</style>
