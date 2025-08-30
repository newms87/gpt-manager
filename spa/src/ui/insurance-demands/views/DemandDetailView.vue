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
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status Timeline -->
                <DemandStatusTimeline
                    :demand="demand"
                    @view-workflow="handleViewWorkflow"
                />

                <!-- Quick Actions -->
                <DemandQuickActions
                    v-if="demand"
                    :demand="demand"
                    @edit="editMode = true"
                />
            </div>
        </div>

        <!-- View Workflow Dialog -->
        <ViewWorkflowDialog
            v-if="showWorkflowDialog"
            :workflow-run="selectedWorkflowRun"
            @close="handleCloseWorkflowDialog"
        />
    </UiMainLayout>
</template>

<script setup lang="ts">
import { usePusher } from "@/helpers/pusher";
import { WorkflowRun } from "@/types";
import { FaSolidExclamation } from "danx-icon";
import { type StoredFile } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { UiLoadingSpinner, UiMainLayout } from "../../shared";
import type { UiDemand } from "../../shared/types";
import {
    DemandDetailDocuments,
    DemandDetailHeader,
    DemandDetailInfo,
    DemandQuickActions,
    DemandStatusTimeline,
    ViewWorkflowDialog
} from "../components/Detail";
import { useDemands } from "../composables";
import { demandRoutes } from "../config";

const route = useRoute();
const router = useRouter();

const { updateDemand } = useDemands();

const demand = ref<UiDemand | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const editMode = ref(false);
const showWorkflowDialog = ref(false);
const selectedWorkflowRun = ref<WorkflowRun | null>(null);

// Track which workflow runs we've already subscribed to
const subscribedWorkflowIds = ref<Set<number>>(new Set());

const demandId = computed(() => {
    const id = route.params.id;
    return typeof id === "string" ? parseInt(id, 10) : null;
});


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
    if (!demandId.value && !isLoading.value) return;

    try {
        isLoading.value = true;
        error.value = null;
        const newDemand = await demandRoutes.details({ id: demandId.value });

        if (!demand.value || demand.value.id !== newDemand.id) {
            demand.value = newDemand;
        }

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
        editMode.value = false;
    } catch (err: any) {
        error.value = err.message || "Failed to update demand";
    }
};

const handleInputFilesUpdate = async (inputFiles: StoredFile[]) => {
    if (!demand.value) return;

    try {
        await updateDemand(demand.value.id, { input_files: inputFiles });
    } catch (err: any) {
        error.value = err.message || "Failed to update input files";
    }
};

const handleOutputFilesUpdate = async (outputFiles: StoredFile[]) => {
    if (!demand.value) return;

    try {
        await updateDemand(demand.value.id, { output_files: outputFiles });
    } catch (err: any) {
        console.error("❌ handleOutputFilesUpdate error:", err);
        error.value = err.message || "Failed to update output files";
    }
};


const handleViewWorkflow = (workflowRun: WorkflowRun) => {
    selectedWorkflowRun.value = workflowRun;
    showWorkflowDialog.value = true;
};

const handleCloseWorkflowDialog = () => {
    showWorkflowDialog.value = false;
    selectedWorkflowRun.value = null;
};

// Watch for route changes and load demand
watch(demandId, (newId, oldId) => {
    // Clear subscriptions when navigating to a different demand
    if (newId !== oldId) {
        subscribedWorkflowIds.value.clear();
    }
    loadDemand();
}, { immediate: true });
</script>
