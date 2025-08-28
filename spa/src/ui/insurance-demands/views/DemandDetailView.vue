<template>
    <UiMainLayout>
        <template #header>
            <DemandDetailHeader
                :demand="demand"
                @back="router.back()"
            >
                <template #actions>
                    <DemandDetailActions
                        :demand="demand"
                        :edit-mode="editMode"
                        :loading-states="loadingStates"
                        @toggle-edit="editMode = !editMode"
                        @extract-data="handleExtractData"
                        @write-demand="handleWriteDemand"
                    />
                </template>
            </DemandDetailHeader>
        </template>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-12">
            <UiLoadingSpinner size="lg" class="text-blue-500" />
            <span class="ml-3 text-slate-600">Loading demand details...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
            <FaSolidExclamation class="w-5 h-5 inline mr-2" />
            {{ error }}
        </div>

        <!-- Main Content -->
        <div v-else-if="demand" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Demand Details -->
                <DemandDetailInfo
                    :demand="demand"
                    :edit-mode="editMode"
                    @update="handleUpdate"
                    @cancel-edit="editMode = false"
                />

                <!-- Documents Section -->
                <DemandDetailDocuments
                    :demand="demand"
                    @update:input-files="handleInputFilesUpdate"
                    @update:output-files="handleOutputFilesUpdate"
                />

                <!-- Workflow Error Display -->
                <UiCard v-if="workflowError" class="border-red-200 bg-red-50">
                    <div class="flex items-start space-x-3">
                        <FaSolidExclamation class="w-5 h-5 text-red-600 mt-0.5" />
                        <div>
                            <h4 class="font-medium text-red-800">Workflow Error</h4>
                            <p class="text-red-700 mt-1">{{ workflowError }}</p>
                        </div>
                    </div>
                </UiCard>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status Timeline -->
                <DemandStatusTimeline :demand="demand" />

                <!-- Quick Actions -->
                <DemandQuickActions
                    :demand="demand"
                    :loading-states="loadingStates"
                    @edit="editMode = true"
                    @extract-data="handleExtractData"
                    @write-demand="handleWriteDemand"
                    @complete="handleComplete"
                    @set-as-draft="handleSetAsDraft"
                    @delete="deleteDemand"
                />
            </div>
        </div>

        <!-- Template Selector Dialog -->
        <DemandTemplateSelector
            v-if="showTemplateSelector"
            @confirm="handleWriteDemandWithTemplate"
            @close="showTemplateSelector = false"
        />
    </UiMainLayout>
</template>

<script setup lang="ts">
import { usePusher } from "@/helpers/pusher";
import { WorkflowRun } from "@/types";
import { FaSolidExclamation } from "danx-icon";
import { type StoredFile, storeObject } from "quasar-ui-danx";
import { computed, ref, watch, watchEffect } from "vue";
import { useRoute, useRouter } from "vue-router";
import { DemandTemplateSelector } from "../../demand-templates/components";
import { UiCard, UiLoadingSpinner, UiMainLayout } from "../../shared";
import type { UiDemand } from "../../shared/types";
import {
    DemandDetailActions,
    DemandDetailDocuments,
    DemandDetailHeader,
    DemandDetailInfo,
    DemandQuickActions,
    DemandStatusTimeline
} from "../components/Detail";
import { useDemands } from "../composables";
import { DEMAND_STATUS, demandRoutes } from "../config";

const route = useRoute();
const router = useRouter();

const {
    updateDemand,
    extractData,
    writeDemand,
    deleteDemand: deleteDemandAction
} = useDemands();

const demand = ref<UiDemand | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const editMode = ref(false);
const workflowError = ref<string | null>(null);
const showTemplateSelector = ref(false);
const isCompleting = ref(false);
const isSettingAsDraft = ref(false);

// Track which workflow runs we've already subscribed to
const subscribedWorkflowIds = ref<Set<number>>(new Set());

const demandId = computed(() => {
    const id = route.params.id;
    return typeof id === "string" ? parseInt(id, 10) : null;
});

// Computed properties for loading states based on workflow status
const extractingData = computed(() => demand.value?.is_extract_data_running || false);
const writingDemand = computed(() => demand.value?.is_write_demand_running || false);

const loadingStates = computed(() => ({
    extractData: extractingData.value,
    writeDemand: writingDemand.value,
    complete: isCompleting.value,
    setAsDraft: isSettingAsDraft.value
}));

// WebSocket subscriptions for real-time WorkflowRun updates
const pusher = usePusher();

const subscribeToWorkflowRunUpdates = () => {
    if (!pusher || !demand.value) {
        console.log("WebSocket subscription not available - pusher:", !!pusher, "demand:", !!demand.value);
        return;
    }

    console.log("Setting up real-time WorkflowRun subscriptions for demand:", demand.value.id);

    // Subscribe to extract data workflow run if not already subscribed
    if (demand.value.extract_data_workflow_run?.id && !subscribedWorkflowIds.value.has(demand.value.extract_data_workflow_run.id)) {
        console.log("Subscribing to extract data workflow run updates:", demand.value.extract_data_workflow_run.id);
        subscribedWorkflowIds.value.add(demand.value.extract_data_workflow_run.id);

        pusher.onModelEvent(
            demand.value.extract_data_workflow_run,
            "updated",
            (updatedWorkflowRun: WorkflowRun) => {
                console.log("Extract data workflow run updated via WebSocket:", {
                    id: updatedWorkflowRun.id,
                    progress: updatedWorkflowRun.progress_percent,
                    status: updatedWorkflowRun.status
                });

                if (updatedWorkflowRun.status === "Completed") {
                    console.log("🎉 Extract Data JUST COMPLETED! Reloading full demand record");
                    // Trigger full demand reload to get updated data
                    loadDemand();
                }
            }
        );
    }

    // Subscribe to write demand workflow run if not already subscribed
    if (demand.value.write_demand_workflow_run?.id && !subscribedWorkflowIds.value.has(demand.value.write_demand_workflow_run.id)) {
        console.log("Subscribing to write demand workflow run updates:", demand.value.write_demand_workflow_run.id);
        subscribedWorkflowIds.value.add(demand.value.write_demand_workflow_run.id);

        pusher.onModelEvent(
            demand.value.write_demand_workflow_run,
            "updated",
            (updatedWorkflowRun: WorkflowRun) => {
                console.log("Write demand workflow run updated via WebSocket:", {
                    id: updatedWorkflowRun.id,
                    progress: updatedWorkflowRun.progress_percent,
                    status: updatedWorkflowRun.status
                });

                if (updatedWorkflowRun.status === "Completed") {
                    console.log("🎉 Write Demand JUST COMPLETED! Reloading full demand record");
                    loadDemand();
                }
            }
        );
    }
};


const loadDemand = async () => {
    if (!demandId.value) return;

    try {
        isLoading.value = true;
        error.value = null;
        const demandData = await demandRoutes.details({ id: demandId.value });

        // Store the demand using storeObject for reactive updates
        demand.value = storeObject(demandData);

        // Subscribe to workflow run updates after demand is loaded
        subscribeToWorkflowRunUpdates();
    } catch (err: any) {
        error.value = err.message || "Failed to load demand";
    } finally {
        isLoading.value = false;
    }
};

const handleUpdate = async (data: { title: string; description: string; input_files?: StoredFile[] }) => {
    if (!demand.value) return;

    try {
        const updatedDemand = await updateDemand(demand.value.id, data);
        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);
        editMode.value = false;
    } catch (err: any) {
        error.value = err.message || "Failed to update demand";
    }
};


const handleExtractData = async () => {
    if (!demand.value) return;

    try {
        workflowError.value = null;
        const updatedDemand = await extractData(demand.value);

        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);

        // Re-subscribe to workflow run updates after starting extract data
        subscribeToWorkflowRunUpdates();
    } catch (err: any) {
        workflowError.value = err.message || "Failed to extract data";
    }
};

const handleWriteDemand = async () => {
    if (!demand.value) return;

    // Show template selector dialog
    showTemplateSelector.value = true;
};

const handleWriteDemandWithTemplate = async (template: any, instructions: string) => {
    console.log("🔍 DemandDetailView - handleWriteDemandWithTemplate called:", {
        template,
        templateId: template?.id,
        templateStoredFileId: template?.stored_file?.id,
        templateStoredFileIdDirect: template?.stored_file_id,
        instructions,
        demand: demand.value?.id
    });

    if (!demand.value) return;

    try {
        workflowError.value = null;
        showTemplateSelector.value = false; // Close the modal
        const updatedDemand = await writeDemand(demand.value, template.id, instructions);

        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);
        console.log("Write demand started with template, demand updated:", {
            id: demand.value.id,
            write_demand_workflow_run: demand.value.write_demand_workflow_run,
            is_write_demand_running: demand.value.is_write_demand_running
        });

        // Re-subscribe to workflow run updates after starting write demand
        subscribeToWorkflowRunUpdates();
    } catch (err: any) {
        workflowError.value = err.message || "Failed to write demand";
    }
};

const handleInputFilesUpdate = async (inputFiles: StoredFile[]) => {
    if (!demand.value) return;

    try {
        const updatedDemand = await updateDemand(demand.value.id, { input_files: inputFiles });
        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);
    } catch (err: any) {
        error.value = err.message || "Failed to update input files";
    }
};

const handleOutputFilesUpdate = async (outputFiles: StoredFile[]) => {
    if (!demand.value) return;

    try {
        console.log("🗂️ handleOutputFilesUpdate called:", {
            demandId: demand.value.id,
            currentOutputFiles: demand.value.output_files?.length || 0,
            newOutputFiles: outputFiles.length,
            outputFiles: outputFiles
        });

        const updatedDemand = await updateDemand(demand.value.id, { output_files: outputFiles });

        console.log("✅ handleOutputFilesUpdate success:", {
            demandId: updatedDemand.id,
            outputFilesCount: updatedDemand.output_files?.length || 0,
            outputFiles: updatedDemand.output_files
        });

        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);
    } catch (err: any) {
        console.error("❌ handleOutputFilesUpdate error:", err);
        error.value = err.message || "Failed to update output files";
    }
};

const handleComplete = async () => {
    if (!demand.value) return;

    try {
        isCompleting.value = true;
        error.value = null;

        const updatedDemand = await updateDemand(demand.value.id, {
            status: DEMAND_STATUS.COMPLETED,
            completed_at: new Date().toISOString()
        });

        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);

        console.log("✅ Demand marked as completed:", demand.value.id);
    } catch (err: any) {
        error.value = err.message || "Failed to mark demand as complete";
        console.error("❌ Failed to complete demand:", err);
    } finally {
        isCompleting.value = false;
    }
};

const handleSetAsDraft = async () => {
    if (!demand.value) return;

    try {
        isSettingAsDraft.value = true;
        error.value = null;

        const updatedDemand = await updateDemand(demand.value.id, {
            status: DEMAND_STATUS.DRAFT,
            completed_at: null
        });

        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);

        console.log("📝 Demand set as draft:", demand.value.id);
    } catch (err: any) {
        error.value = err.message || "Failed to set demand as draft";
        console.error("❌ Failed to set demand as draft:", err);
    } finally {
        isSettingAsDraft.value = false;
    }
};

const deleteDemand = async () => {
    if (!demand.value) return;

    if (confirm("Are you sure you want to delete this demand? This action cannot be undone.")) {
        try {
            await deleteDemandAction(demand.value.id);
            router.push("/ui/demands");
        } catch (err: any) {
            error.value = err.message || "Failed to delete demand";
        }
    }
};

// Watch for route changes and load demand
watch(demandId, (newId, oldId) => {
    // Clear subscriptions when navigating to a different demand
    if (newId !== oldId) {
        subscribedWorkflowIds.value.clear();
    }
    loadDemand();
}, { immediate: true });


// Debug logging for demand state changes
watchEffect(() => {
    if (demand.value) {
        console.log("🔍 DemandDetailView - Demand State Changed:", {
            demand_id: demand.value.id,
            can_extract_data: demand.value.can_extract_data,
            can_write_demand: demand.value.can_write_demand,
            is_extract_data_running: demand.value.is_extract_data_running,
            is_write_demand_running: demand.value.is_write_demand_running,
            metadata: demand.value.metadata,
            extract_data_completed_at: demand.value.metadata?.extract_data_completed_at,
            team_object_id: demand.value.team_object_id,
            extract_workflow_run: demand.value.extract_data_workflow_run ? {
                id: demand.value.extract_data_workflow_run.id,
                status: demand.value.extract_data_workflow_run.status,
                progress: demand.value.extract_data_workflow_run.progress_percent,
                completed_at: demand.value.extract_data_workflow_run.completed_at
            } : null,
            write_workflow_run: demand.value.write_demand_workflow_run ? {
                id: demand.value.write_demand_workflow_run.id,
                status: demand.value.write_demand_workflow_run.status,
                progress: demand.value.write_demand_workflow_run.progress_percent,
                completed_at: demand.value.write_demand_workflow_run.completed_at
            } : null
        });

        if (!demand.value.can_write_demand) {
            console.log("❌ DemandDetailView - Write Demand NOT AVAILABLE. Checking conditions:");
            console.log("  - extract_data_completed_at:", demand.value.metadata?.extract_data_completed_at);
            console.log("  - team_object_id:", demand.value.team_object_id);
            console.log("  - is_write_demand_running:", demand.value.is_write_demand_running);
            console.log("  - extract_data_workflow_run status:", demand.value.extract_data_workflow_run?.status);
            console.log("  - extract_data_workflow_run completed_at:", demand.value.extract_data_workflow_run?.completed_at);
        } else {
            console.log("✅ DemandDetailView - Write Demand AVAILABLE");
        }
    }
});

// Note: WebSocket subscriptions are automatically managed by the pusher helper
</script>
