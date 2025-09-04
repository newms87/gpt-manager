<template>
    <FullScreenDialog
        :model-value="true"
        closeable
        content-class="bg-slate-900 p-0 flex flex-col h-full"
        @close="$emit('close')"
    >
        <!-- Header -->
        <div class="bg-slate-800 p-4 border-b border-slate-700 flex-shrink-0">
            <div class="flex-x space-x-2">
                <h2 class="text-lg font-semibold text-slate-200">
                    Workflow: {{ workflowRun?.name || "Loading..." }}
                </h2>
                <LabelPillWidget
                    v-if="workflowRun"
                    :label="workflowRun.id"
                    color="sky"
                    size="xs"
                    class="flex-shrink-0"
                />
            </div>
            <div class="flex items-center gap-3 mt-1">
                <div class="text-sm text-slate-400">
                    Status: <span :class="statusColor">{{ workflowRun?.status }}</span>
                </div>
                <div v-if="workflowRun?.progress_percent !== undefined" class="text-sm text-slate-400">
                    Progress: <span class="text-blue-400">{{ workflowRun.progress_percent }}%</span>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-grow min-h-0">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <QSpinnerGears class="text-sky-500 w-12 h-12 mb-3" />
                    <p class="text-slate-400">Loading workflow definition...</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <FaSolidTriangleExclamation class="w-8 h-8 text-red-500 mb-3 mx-auto" />
                    <p class="text-red-400 mb-2">Failed to load workflow</p>
                    <p class="text-slate-500 text-sm">{{ error }}</p>
                </div>
            </div>

            <!-- Workflow Canvas -->
            <div v-else-if="workflowDefinition" class="h-full w-full">
                <WorkflowCanvas
                    class="h-full"
                    v-model="workflowDefinition"
                    :workflow-run="workflowRun"
                    :loading="false"
                    readonly
                />
            </div>
        </div>
    </FullScreenDialog>
</template>

<script setup lang="ts">
import WorkflowCanvas from "@/components/Modules/WorkflowCanvas/WorkflowCanvas.vue";
import { routes as workflowDefinitionRoutes } from "@/components/Modules/WorkflowDefinitions/config/routes";
import { refreshWorkflowRun } from "@/components/Modules/WorkflowDefinitions/store";
import { usePusher } from "@/helpers/pusher";
import { TaskRun, WorkflowDefinition, WorkflowRun } from "@/types";
import { FaSolidTriangleExclamation } from "danx-icon";
import { FullScreenDialog, LabelPillWidget } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const emit = defineEmits<{
    close: [];
}>();

const props = defineProps<{
    workflowRun: WorkflowRun;
}>();

const workflowDefinition = ref<WorkflowDefinition | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const pusher = usePusher();

const statusColor = computed(() => {
    if (!props.workflowRun?.status) return "text-slate-400";

    switch (props.workflowRun.status) {
        case "Completed":
            return "text-green-400";
        case "Running":
        case "Pending":
            return "text-blue-400";
        case "Failed":
            return "text-red-400";
        case "Skipped":
            return "text-yellow-400";
        default:
            return "text-slate-400";
    }
});

const loadWorkflowDefinition = async () => {
    if (!props.workflowRun?.workflow_definition_id) {
        error.value = "No workflow definition ID found";
        return;
    }

    try {
        loading.value = true;
        error.value = null;

        workflowDefinition.value = await workflowDefinitionRoutes.details({
            id: props.workflowRun.workflow_definition_id
        });
    } catch (err: any) {
        console.error("Failed to load workflow definition:", err);
        error.value = err.message || "Failed to load workflow definition";
    } finally {
        loading.value = false;
    }
};

// Store the callback reference for proper cleanup
const onWorkflowRunUpdate = (updatedWorkflowRun: WorkflowRun) => {
    if (updatedWorkflowRun.id === props.workflowRun?.id) {
        refreshWorkflowRun(props.workflowRun);
    }
};

const onTaskRunCreated = (taskRun: TaskRun) => {
    if (taskRun.workflow_run_id === props.workflowRun?.id) {
        refreshWorkflowRun(props.workflowRun);
    }
};

const loadOnChange = () => {
    loadWorkflowDefinition();

    // First unsubscribe from any previous subscription
    unsubscribeFromUpdates();
    subscribeToUpdates();
};

const subscribeToUpdates = () => {
    // Subscribe to workflow run updates for real-time status changes
    pusher.onModelEvent(props.workflowRun, "updated", onWorkflowRunUpdate);
    pusher.onEvent("TaskRun", "created", onTaskRunCreated);
};

const unsubscribeFromUpdates = () => {
    // Clean up the subscription using the same callback reference
    pusher.offModelEvent(props.workflowRun, "updated", onWorkflowRunUpdate);
    pusher.offEvent("TaskRun", "created", onTaskRunCreated);
};

onMounted(loadOnChange);
onUnmounted(unsubscribeFromUpdates);
watch(() => props.workflowRun, loadOnChange);
</script>
