import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { authTeam } from "@/helpers/auth";
import { usePusher } from "@/helpers/pusher";
import {
    TaskDefinition,
    TaskRun,
    TaskRunnerClass,
    WorkflowDefinition,
    WorkflowInput,
    WorkflowNode,
    WorkflowRun
} from "@/types";
import { getItem, setItem } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import { Router } from "vue-router";

const ACTIVE_WORKFLOW_DEFINITION_KEY = "dx-active-workflow-definition-id";

const isLoadingWorkflowDefinitions = ref(false);
const workflowLoadError = ref<string | null>(null);
const activeWorkflowDefinition = ref<WorkflowDefinition>(null);
const activeWorkflowRun = ref<WorkflowRun>(null);
const workflowDefinitions = ref<WorkflowDefinition[]>([]);
const pusher = usePusher();

// Computed property for read-only access control
const isReadOnly = computed(() => {
    if (!activeWorkflowDefinition.value || !authTeam.value) return false;

    // System workflows (team_id === null) are read-only for non-system teams
    if (activeWorkflowDefinition.value.team_id === null) return true;

    // Workflows from other teams are read-only
    return activeWorkflowDefinition.value.team_id !== authTeam.value.id;
});

// Track active TaskRun subscription for cleanup
let activeTaskRunSubscription: { workflowRunId: number } | null = null;

// Subscribe to TaskRun events filtered by workflow_run_id
watch(activeWorkflowRun, async (newRun, oldRun) => {
    if (!pusher) return;

    // Unsubscribe from old workflow run's TaskRuns if exists
    if (oldRun?.id && activeTaskRunSubscription) {
        await pusher.unsubscribeFromModel("TaskRun", ["created", "updated"], {
            filter: { workflow_run_id: oldRun.id }
        });
        activeTaskRunSubscription = null;
    }

    // Subscribe to new workflow run's TaskRuns
    if (newRun?.id) {
        await pusher.subscribeToModel("TaskRun", ["created", "updated"], {
            filter: { workflow_run_id: newRun.id }
        });
        activeTaskRunSubscription = { workflowRunId: newRun.id };
    }
});

async function refreshActiveWorkflowDefinition() {
    await dxWorkflowDefinition.routes.details(activeWorkflowDefinition.value);
}

async function setActiveWorkflowDefinition(workflowDefinition: string | number | WorkflowDefinition | null, router?: Router, fromUrl: boolean = false) {
    const workflowDefinitionId = typeof workflowDefinition === "object" ? workflowDefinition?.id : workflowDefinition;
    setItem(ACTIVE_WORKFLOW_DEFINITION_KEY, workflowDefinitionId);

    // Clear active run if the definition has changed
    if (activeWorkflowDefinition.value?.id !== workflowDefinitionId) {
        activeWorkflowRun.value = null;
    }

    // Clear previous error state
    workflowLoadError.value = null;

    // Find workflow in loaded list (with null check)
    activeWorkflowDefinition.value = workflowDefinitions.value?.find((tw) => tw.id === workflowDefinitionId) || null;

    // Only update URL if not already coming from URL navigation
    if (router && workflowDefinitionId && activeWorkflowDefinition.value && !fromUrl) {
        await router.push({
            name: "workflow-definitions",
            params: { id: workflowDefinitionId.toString() }
        });
    }

    if (activeWorkflowDefinition.value) {
        await dxWorkflowDefinition.routes.details(activeWorkflowDefinition.value);
        await loadWorkflowRuns();
    }
}

async function fetchWorkflowById(workflowId: number): Promise<WorkflowDefinition | null> {
    try {
        // Create a temporary workflow object to use with the details route
        const tempWorkflow = { id: workflowId } as WorkflowDefinition;

        // Use the same details route that all other workflows use
        const response = await dxWorkflowDefinition.routes.details(tempWorkflow);

        // Validate that we got a proper workflow object
        if (!response || !response.id || response.error) {
            throw new Error('Invalid workflow response');
        }

        // Return the fetched workflow
        // Do NOT add to workflowDefinitions list - keep list pure (team-only workflows)
        return response as WorkflowDefinition;
    } catch (error) {
        console.error(`Failed to fetch workflow ${workflowId}:`, error);

        // Set appropriate error message based on error type
        if (error.response?.status === 404 || error.message?.includes('404')) {
            workflowLoadError.value = `Workflow ${workflowId} not found`;
        } else if (error.response?.status === 403 || error.message?.includes('403')) {
            workflowLoadError.value = `You don't have permission to view workflow ${workflowId}`;
        } else {
            workflowLoadError.value = `Failed to load workflow ${workflowId}`;
        }

        return null;
    }
}

async function initWorkflowState(urlWorkflowId?: number, router?: Router) {
    await loadWorkflowDefinitions();

    // URL has absolute priority - NEVER redirect if URL contains a workflow ID
    if (urlWorkflowId) {
        // First try to find in loaded list (team workflows)
        const existingWorkflow = workflowDefinitions.value?.find(w => w.id === urlWorkflowId);

        if (existingWorkflow) {
            // Found in team list - set it normally
            await setActiveWorkflowDefinition(urlWorkflowId, router, true);
        } else {
            // Not in team list - try to fetch directly (might be system workflow)
            const fetchedWorkflow = await fetchWorkflowById(urlWorkflowId);
            if (fetchedWorkflow) {
                // Successfully fetched (system or accessible workflow)
                activeWorkflowDefinition.value = fetchedWorkflow;
                setItem(ACTIVE_WORKFLOW_DEFINITION_KEY, urlWorkflowId);
                await loadWorkflowRuns();
            } else {
                // Failed to fetch - error already set by fetchWorkflowById
                activeWorkflowDefinition.value = null;
                // Clear localStorage since this workflow doesn't exist or isn't accessible
                setItem(ACTIVE_WORKFLOW_DEFINITION_KEY, null);
            }
        }
    } else {
        // No URL workflow ID - fall back to localStorage or first available
        const storedId = getItem(ACTIVE_WORKFLOW_DEFINITION_KEY);
        if (storedId && workflowDefinitions.value?.find(w => w.id === storedId)) {
            await setActiveWorkflowDefinition(storedId, router);
        } else if (workflowDefinitions.value?.length > 0) {
            await setActiveWorkflowDefinition(workflowDefinitions.value[0], router);
        }
    }
}

async function loadWorkflowDefinitions() {
    try {
        isLoadingWorkflowDefinitions.value = true;
        workflowLoadError.value = null;
        const result = await dxWorkflowDefinition.routes.list();
        workflowDefinitions.value = result.data || [];
    } catch (error) {
        console.error("Failed to load workflow definitions:", error);
        workflowLoadError.value = "Failed to load workflow definitions";
        workflowDefinitions.value = [];
    } finally {
        isLoadingWorkflowDefinitions.value = false;
    }
}

async function loadWorkflowRuns() {
    if (!activeWorkflowDefinition.value) return;
    return await dxWorkflowDefinition.routes.details(activeWorkflowDefinition.value, { "*": false, runs: true });
}

const refreshQueue = ref([]);
const refreshingWorkflowRun = ref(false);

async function refreshWorkflowRun(workflowRun?: WorkflowRun) {
    if (workflowRun) {
        // Add the workflow run to the refresh queue
        refreshQueue.value.push(workflowRun);
    }

    // If a workflow run is already being refreshed, exit
    if (refreshingWorkflowRun.value) return;

    // FIFO - process the first workflow run in the queue
    refreshingWorkflowRun.value = refreshQueue.value.shift();

    // Stop if there's no workflow run to refresh
    if (!refreshingWorkflowRun.value) return;

    // Clear duplicates from the queue
    refreshQueue.value = refreshQueue.value.filter(wr => wr.id === refreshingWorkflowRun.value.id);

    await dxWorkflowRun.routes.details(refreshingWorkflowRun.value, { taskRuns: { taskDefinition: true } });

    // Keep running the queue until it's empty
    refreshingWorkflowRun.value = null;
    await refreshWorkflowRun();
}

const addNodeAction = dxWorkflowDefinition.getAction("add-node", {
    optimistic: (action, target: WorkflowDefinition, data: WorkflowNode) => target.nodes.push({ ...data }),
    onFinish: async () => await refreshWorkflowRun(activeWorkflowRun.value)
});

async function addWorkflowNode(newNode: TaskDefinition | TaskRunnerClass, input: Partial<WorkflowNode> = {}) {
    return await addNodeAction.trigger(activeWorkflowDefinition.value, {
        id: "td-" + newNode.name,
        name: newNode.name,
        task_definition_id: newNode.id || null,
        task_runner_name: newNode.id ? null : newNode.name,
        ...input
    });
}

const createWorkflowRunAction = dxWorkflowRun.getAction("create", { onFinish: loadWorkflowRuns });
const isCreatingWorkflowRun = ref(false);

async function createWorkflowRun(workflowInput?: WorkflowInput) {
    if (!activeWorkflowDefinition.value) return;
    activeWorkflowRun.value = null;

    isCreatingWorkflowRun.value = true;
    const result = await createWorkflowRunAction.trigger(null, {
        workflow_definition_id: activeWorkflowDefinition.value.id,
        workflow_input_id: workflowInput?.id
    });
    isCreatingWorkflowRun.value = false;

    activeWorkflowRun.value = result.item;
}

export {
    isLoadingWorkflowDefinitions,
    isCreatingWorkflowRun,
    workflowLoadError,
    isReadOnly,
    activeWorkflowDefinition,
    activeWorkflowRun,
    workflowDefinitions,
    initWorkflowState,
    refreshActiveWorkflowDefinition,
    refreshWorkflowRun,
    createWorkflowRun,
    loadWorkflowDefinitions,
    loadWorkflowRuns,
    setActiveWorkflowDefinition,
    addWorkflowNode,
    fetchWorkflowById
};
