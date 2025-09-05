<template>
    <UiCard class="border-teal-200 bg-teal-50">
        <template #header>
            <div class="flex items-center space-x-2">
                <FaSolidNotesMedical class="w-5 h-5 text-teal-600" />
                <h3 class="text-lg font-semibold text-teal-800">
                    Medical Summaries
                </h3>
                <span class="bg-teal-100 text-teal-700 text-xs px-2 py-1 rounded-full">
          {{ medicalSummaries.length }}
        </span>
            </div>
        </template>

        <div class="space-y-3">
            <p class="text-teal-700 text-sm">
                Generated medical summaries from workflow processing.
            </p>

            <div v-if="medicalSummaries.length > 0">
                <div class="space-y-3">
                    <div
                        v-for="(summary, index) in medicalSummaries"
                        :key="summary.id"
                        class="border border-teal-200 rounded-lg bg-white"
                    >
                        <!-- Summary Header -->
                        <div class="px-4 py-3 border-b border-teal-100 bg-teal-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <FaSolidFile class="w-4 h-4 text-teal-600" />
                                    <h4 class="text-sm font-medium text-teal-800">
                                        {{ summary.name || `Medical Summary ${index + 1}` }}
                                    </h4>
                                </div>
                                <div class="flex items-center space-x-2">
                  <span v-if="summary.type" class="text-xs text-teal-600 bg-teal-100 px-2 py-1 rounded">
                    {{ summary.type }}
                  </span>
                                    <ShowHideButton
                                        v-if="isExpanded"
                                        v-model="isEditing"
                                        type="edit"
                                        color="teal"
                                        size="xs"
                                        :show-icon="EditIcon"
                                        tooltip="Edit"
                                        @click="toggleEdit(summary)"
                                    />
                                    <ShowHideButton
                                        v-model="isExpanded"
                                        size="xs"
                                        color="teal-invert"
                                        @click="toggleSummary(summary.id)"
                                    />
                                    <ActionButton
                                        type="trash"
                                        color="red"
                                        size="xs"
                                        tooltip="Delete"
                                        @click="deleteSummary(summary)"
                                    />
                                </div>
                            </div>
                            <p v-if="summary.created_at" class="text-xs text-teal-600 mt-1">
                                Generated {{ fDateTime(summary.created_at, { format: "MMM d, yyyy h:mm a" }) }}
                            </p>
                        </div>

                        <!-- Summary Content -->
                        <div
                            v-if="expandedSummaries.has(summary.id)"
                            class="p-4"
                        >
                            <div v-if="editingSummaries.has(summary.id)">
                                <!-- Editing Mode -->
                                <div class="space-y-4">
                                    <MarkdownEditor
                                        v-model="editingContent[summary.id]"
                                        :readonly="false"
                                        :height="400"
                                        class="border border-teal-200 rounded"
                                    />
                                    <div class="flex items-center justify-end space-x-2">
                                        <ActionButton
                                            type="cancel"
                                            color="gray"
                                            size="sm"
                                            label="Cancel"
                                            @click="cancelEdit(summary)"
                                        />
                                        <ActionButton
                                            type="save"
                                            color="teal"
                                            size="sm"
                                            label="Save"
                                            @click="saveSummary(summary)"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else>
                                <!-- View Mode -->
                                <div v-if="summary.text_content">
                                    <MarkdownEditor
                                        :model-value="summary.text_content"
                                        :readonly="true"
                                        :height="400"
                                        class="border border-teal-200 rounded"
                                        :editor-class="{'bg-slate-100': !isEditing, 'bg-slate-200 text-slate-800': isEditing}"
                                    />
                                </div>
                                <div v-else class="text-gray-500 italic p-4 bg-gray-50 rounded">
                                    No content available
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-8">
                <div class="bg-teal-50 rounded-lg p-6 border border-teal-200">
                    <FaSolidNotesMedical class="w-12 h-12 text-teal-400 mx-auto mb-3" />
                    <h4 class="text-sm font-medium text-teal-800 mb-2">No Medical Summaries Yet</h4>
                    <p class="text-xs text-teal-600">Complete the "Write Medical Summary" workflow step to generate and
                        view summaries here.</p>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <ConfirmDialog
            v-if="summaryToDelete"
            title="Delete Medical Summary"
            :content="`Are you sure you want to delete '${summaryToDelete.name || 'this medical summary'}'? This action cannot be undone.`"
            confirm-text="Delete"
            confirm-color="red"
            @confirm="confirmDelete"
            @close="summaryToDelete = null"
        />
    </UiCard>
</template>

<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import { FaSolidFile, FaSolidNotesMedical, FaSolidPencil as EditIcon } from "danx-icon";
import { ActionButton, ConfirmDialog, fDateTime, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { UiCard } from "../../../shared";
import type { Artifact, UiDemand } from "../../../shared/types";

const props = defineProps<{
    demand: UiDemand | null;
}>();

const isExpanded = ref(false);
const isEditing = ref(false);
// Computed property for medical summaries
const medicalSummaries = computed(() => props.demand?.medical_summaries || []);

// Track expanded state for each summary
const expandedSummaries = ref<Set<number>>(new Set());

// Track editing state for each summary
const editingSummaries = ref<Set<number>>(new Set());
const editingContent = ref<Record<number, string>>({});

// Track deletion state
const summaryToDelete = ref<Artifact | null>(null);

// Toggle summary expansion
const toggleSummary = (summaryId: number) => {
    if (expandedSummaries.value.has(summaryId)) {
        expandedSummaries.value.delete(summaryId);
    } else {
        expandedSummaries.value.add(summaryId);
    }
};

// Toggle editing mode
const toggleEdit = (summary: Artifact) => {
    if (editingSummaries.value.has(summary.id)) {
        editingSummaries.value.delete(summary.id);
        delete editingContent.value[summary.id];
    } else {
        editingSummaries.value.add(summary.id);
        editingContent.value[summary.id] = summary.text_content || "";
    }
};

// Save summary content
const saveSummary = async (summary: Artifact) => {
    try {
        const content = editingContent.value[summary.id];
        await dxArtifact.getAction("update").trigger(summary, { text_content: content });
        editingSummaries.value.delete(summary.id);
        delete editingContent.value[summary.id];
    } catch (error) {
        console.error("Error saving summary:", error);
    }
};

// Cancel editing
const cancelEdit = (summary: Artifact) => {
    editingSummaries.value.delete(summary.id);
    delete editingContent.value[summary.id];
};

// Delete summary
const deleteSummary = (summary: Artifact) => {
    summaryToDelete.value = summary;
};

// Confirm deletion
const confirmDelete = async () => {
    if (!summaryToDelete.value) return;

    try {
        await dxArtifact.getAction("quick-delete").trigger(summaryToDelete.value);
        summaryToDelete.value = null;
    } catch (error) {
        console.error("Error deleting summary:", error);
    }
};

// Auto-expand first summary on mount if there's only one
if (medicalSummaries.value.length === 1) {
    expandedSummaries.value.add(medicalSummaries.value[0].id);
}
</script>
