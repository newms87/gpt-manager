<template>
    <UiMainLayout>
        <template #header>
            <DemandDetailHeader
                ref="headerRef"
                :demand="demand"
                @mark-complete="handleMarkComplete"
                @set-draft="handleSetAsDraft"
                @delete="handleDeleteDemand"
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
                    @view-data="handleViewData"
                    @run-workflow="handleRunWorkflow"
                />

                <!-- Usage Display -->
                <UsageDisplayContainer
                    :demand="demand"
                    :default-expanded="true"
                    :allow-collapse="false"
                />
            </div>
        </div>

        <!-- View Workflow Dialog -->
        <ViewWorkflowDialog
            v-if="showWorkflowDialog"
            :workflow-run="selectedWorkflowRun"
            @close="handleCloseWorkflowDialog"
        />

        <!-- View Data Dialog -->
        <TeamObjectDataDialog
            v-if="showDataDialog"
            :team-object="demand?.team_object || null"
            @close="handleCloseDataDialog"
        />
    </UiMainLayout>
</template>

<script setup lang="ts">
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
    DemandStatusTimeline,
    TeamObjectDataDialog,
    ViewWorkflowDialog
} from "../components/Detail";
import { UsageDisplayContainer } from "../components/Usage";
import { useDemands } from "../composables";
import { DEMAND_STATUS } from "../config";

const route = useRoute();
const router = useRouter();

const { updateDemand, loadDemand, runWorkflow, subscribeToWorkflowRunUpdates, clearWorkflowSubscriptions, deleteDemand } = useDemands();

const demand = ref<UiDemand | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const editMode = ref(false);
const showWorkflowDialog = ref(false);
const selectedWorkflowRun = ref<WorkflowRun | null>(null);
const showDataDialog = ref(false);
const headerRef = ref<InstanceType<typeof DemandDetailHeader> | null>(null);

const demandId = computed(() => {
    const id = route.params.id;
    return typeof id === "string" ? parseInt(id, 10) : null;
});

// Load demand with WebSocket subscriptions
const loadDemandWithSubscriptions = async () => {
    if (!demandId.value || isLoading.value) return;

    try {
        isLoading.value = true;
        error.value = null;
        const newDemand = await loadDemand(demandId.value);

        demand.value = newDemand;

        // Subscribe to workflow run updates after demand is loaded
        subscribeToWorkflowRunUpdates(newDemand, (updatedDemand) => {
            demand.value = updatedDemand;
        });
    } catch (err: any) {
        error.value = err.message || "Failed to load demand";
    } finally {
        isLoading.value = false;
    }
};

const handleUpdate = async (data: { title: string; description: string; input_files?: StoredFile[] }) => {
    if (!demand.value) return;

    try {
        await updateDemand(demand.value.id, data);
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

const handleViewData = () => {
    showDataDialog.value = true;
};

const handleCloseDataDialog = () => {
    showDataDialog.value = false;
};

const handleRunWorkflow = async (workflowKey: string, parameters?: Record<string, any>) => {
    if (!demand.value) return;

    try {
        await runWorkflow(demand.value, workflowKey, parameters, (updatedDemand) => {
            demand.value = updatedDemand;
        });
    } catch (err: any) {
        error.value = err.message || `Failed to run workflow: ${workflowKey}`;
    }
};

const handleMarkComplete = async () => {
    if (!demand.value) return;

    try {
        headerRef.value?.setCompletingOrSettingDraft(true);

        await updateDemand(demand.value.id, {
            status: DEMAND_STATUS.COMPLETED,
            completed_at: new Date().toISOString()
        });
    } catch (err: any) {
        error.value = err.message || "Failed to mark demand as complete";
        console.error("Failed to mark demand as complete:", err);
    } finally {
        headerRef.value?.setCompletingOrSettingDraft(false);
    }
};

const handleSetAsDraft = async () => {
    if (!demand.value) return;

    try {
        headerRef.value?.setCompletingOrSettingDraft(true);

        await updateDemand(demand.value.id, {
            status: DEMAND_STATUS.DRAFT,
            completed_at: null
        });
    } catch (err: any) {
        error.value = err.message || "Failed to set demand as draft";
        console.error("Failed to set demand as draft:", err);
    } finally {
        headerRef.value?.setCompletingOrSettingDraft(false);
    }
};

const handleDeleteDemand = async () => {
    if (!demand.value) return;

    try {
        headerRef.value?.setDeleting(true);
        await deleteDemand(demand.value.id);
        router.push("/ui/demands");
    } catch (err: any) {
        error.value = err.message || "Failed to delete demand";
        console.error("Failed to delete demand:", err);
    } finally {
        headerRef.value?.setDeleting(false);
    }
};

// Watch for route changes and load demand
watch(demandId, (newId, oldId) => {
    // Clear subscriptions when navigating to a different demand
    if (newId !== oldId) {
        clearWorkflowSubscriptions();
    }
    loadDemandWithSubscriptions();
}, { immediate: true });
</script>
