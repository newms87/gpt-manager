<template>
    <UiMainLayout>
        <template #header>
            <DemandDetailHeader
                :demand="demand"
                @back="router.back()"
            />
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
                    v-model:edit-mode="editMode"
                    @update="handleUpdate"
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
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { DemandTemplateSelector } from "../../demand-templates/components";
import { UiCard, UiLoadingSpinner, UiMainLayout } from "../../shared";
import type { UiDemand } from "../../shared/types";
import {
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
        return;
    }


    // Subscribe to extract data workflow run if not already subscribed
    if (demand.value.extract_data_workflow_run?.id && !subscribedWorkflowIds.value.has(demand.value.extract_data_workflow_run.id)) {
        subscribedWorkflowIds.value.add(demand.value.extract_data_workflow_run.id);

        pusher.onModelEvent(
            demand.value.extract_data_workflow_run,
            "updated",
            (updatedWorkflowRun: WorkflowRun) => {

                if (updatedWorkflowRun.status === "Completed") {
                    // Trigger full demand reload to get updated data
                    loadDemand();
                }
            }
        );
    }

    // Subscribe to write demand workflow run if not already subscribed
    if (demand.value.write_demand_workflow_run?.id && !subscribedWorkflowIds.value.has(demand.value.write_demand_workflow_run.id)) {
        subscribedWorkflowIds.value.add(demand.value.write_demand_workflow_run.id);

        pusher.onModelEvent(
            demand.value.write_demand_workflow_run,
            "updated",
            (updatedWorkflowRun: WorkflowRun) => {

                if (updatedWorkflowRun.status === "Completed") {
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

    if (!demand.value) return;

    try {
        workflowError.value = null;
        showTemplateSelector.value = false; // Close the modal
        const updatedDemand = await writeDemand(demand.value, template.id, instructions);

        // Store the updated demand using storeObject for reactive updates
        demand.value = storeObject(updatedDemand);

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

        const updatedDemand = await updateDemand(demand.value.id, { output_files: outputFiles });


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


// Note: WebSocket subscriptions are automatically managed by the pusher helper
</script>
