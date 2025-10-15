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
                    <MedicalSummaryItem
                        v-for="(summary, index) in medicalSummaries"
                        :key="summary.id"
                        :summary="summary"
                        :index="index"
                        :auto-expand="medicalSummaries.length === 1"
                        @update="saveSummary"
                        @delete="deleteSummary"
                    />
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
            class="ui-mode"
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
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import { FaSolidNotesMedical } from "danx-icon";
import { ConfirmDialog } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { UiCard } from "../../../shared";
import type { Artifact, UiDemand } from "../../../shared/types";
import MedicalSummaryItem from "./MedicalSummaryItem.vue";

const props = defineProps<{
    demand: UiDemand | null;
}>();

// Computed property for medical summaries
const medicalSummaries = computed(() => props.demand?.medical_summaries || []);

// Track deletion state
const summaryToDelete = ref<Artifact | null>(null);

// Save summary content
const saveSummary = async (summary: Artifact, content: string) => {
    try {
        await dxArtifact.getAction("update").trigger(summary, { text_content: content });
    } catch (error) {
        console.error("Error saving summary:", error);
    }
};

// Delete summary
const deleteSummary = (summary: Artifact) => {
    summaryToDelete.value = summary;
};

// Confirm deletion
const confirmDelete = async () => {
    if (!summaryToDelete.value) return;

    try {
        await dxArtifact.getAction("delete").trigger(summaryToDelete.value);
        summaryToDelete.value = null;
    } catch (error) {
        console.error("Error deleting summary:", error);
    }
};
</script>
