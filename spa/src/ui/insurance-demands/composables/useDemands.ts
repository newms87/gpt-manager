import { usePusher } from "@/helpers/pusher";
import { WorkflowRun } from "@/types";
import { FlashMessages, storeObject } from "quasar-ui-danx";
import { computed, ref } from "vue";
import type { UiDemand } from "../../shared/types";
import { DEMAND_STATUS, demandRoutes } from "../config";

const demands = ref<UiDemand[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);

// WebSocket subscriptions tracking
const subscribedWorkflowIds = ref<Set<number>>(new Set());
const pusher = usePusher();

// Debounce state for loadDemand
const loadDemandDebounceMap = ref<Map<number, {
    isLoading: boolean;
    lastCallTime: number;
    queuedResolvers: Array<{ resolve: (value: any) => void; reject: (error: any) => void }>;
    timeoutId: NodeJS.Timeout | null;
}>>(new Map());

export function useDemands() {
    const sortedDemands = computed(() => {
        return [...demands.value].sort((a, b) =>
            new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        );
    });

    const demandsByStatus = computed(() => {
        return demands.value.reduce((acc, demand) => {
            if (!acc[demand.status]) {
                acc[demand.status] = [];
            }
            acc[demand.status].push(demand);
            return acc;
        }, {} as Record<string, UiDemand[]>);
    });

    const stats = computed(() => ({
        total: demands.value.length,
        draft: demands.value.filter(d => d.status === DEMAND_STATUS.DRAFT).length,
        completed: demands.value.filter(d => d.status === DEMAND_STATUS.COMPLETED).length,
        failed: demands.value.filter(d => d.status === DEMAND_STATUS.FAILED).length
    }));

    const loadDemands = async () => {
        try {
            isLoading.value = true;
            error.value = null;
            const response = await demandRoutes.list();
            demands.value = response.data;
        } catch (err: any) {
            error.value = err.message || "Failed to load demands";
            console.error("Error loading demands:", err);
        } finally {
            isLoading.value = false;
        }
    };

    const createDemand = async (data: Partial<UiDemand>) => {
        const result = await demandRoutes.applyAction("create", null, data);

        if (result.success) {
            // Reload the demands list to include the new demand
            await loadDemands();
        }

        return result;
    };

    const updateDemand = async (id: number, data: Partial<UiDemand>) => {
        try {
            const response = await demandRoutes.applyAction("update", { id }, data);
            return response.item;

        } catch (err: any) {
            error.value = err.message || "Failed to update demand";
            throw err;
        }
    };

    const deleteDemand = async (id: number) => {
        try {
            await demandRoutes.applyAction("delete", { id });
            demands.value = demands.value.filter(d => d.id !== id);
        } catch (err: any) {
            error.value = err.message || "Failed to delete demand";
            throw err;
        }
    };


    async function extractData(demand: UiDemand, onDemandUpdate?: (updatedDemand: UiDemand) => void) {
        try {
            const response = await demandRoutes.extractData(demand);

            // Check if response is an error (has error or message fields indicating failure)
            if (response?.error) {
                throw new Error(response.error);
            } else {
                storeObject(response);
                // Subscribe to workflow run updates after starting extract data
                subscribeToWorkflowRunUpdates(demand, onDemandUpdate);
            }
        } catch (err: any) {
            const errorMessage = err?.response?.data?.error || err?.response?.data?.message || err.message || "Failed to extract data";
            error.value = errorMessage;
            FlashMessages.error(errorMessage);

            // Don't update the demand object with error response - just throw the error
            throw err;
        }
    }

    async function writeDemand(demand: UiDemand, templateId?: string, additionalInstructions?: string, onDemandUpdate?: (updatedDemand: UiDemand) => void) {
        try {
            const data: any = {};
            if (templateId) {
                data.template_id = templateId;
            }
            if (additionalInstructions) {
                data.additional_instructions = additionalInstructions;
            }

            const response = await demandRoutes.writeDemand(demand, data);

            // Check if response is an error (has error or message fields indicating failure)
            if (response?.error) {
                throw new Error(response.error);
            } else {
                storeObject(response);
                // Subscribe to workflow run updates after starting write demand
                subscribeToWorkflowRunUpdates(demand, onDemandUpdate);
            }
        } catch (err: any) {
            const errorMessage = err?.response?.data?.error || err?.response?.data?.message || err.message || "Failed to write demand";
            error.value = errorMessage;
            FlashMessages.error(errorMessage);

            // Don't update the demand object with error response - just throw the error
            throw err;
        }
    }

    // Internal function to actually load the demand
    const _loadDemandInternal = async (demandId: number) => {
        try {
            return await demandRoutes.details({ id: demandId }, {
                user: true,
                input_files: { thumb: true },
                output_files: { thumb: true },
                team_object: true,
                extract_data_workflow_run: true,
                write_demand_workflow_run: true
            });
        } catch (err: any) {
            const errorMessage = err.message || "Failed to load demand";
            error.value = errorMessage;
            throw new Error(errorMessage);
        }
    };

    // Debounced load demand function with immediate execution and queuing
    const loadDemand = async (demandId: number): Promise<any> => {
        return new Promise((resolve, reject) => {
            const now = Date.now();
            const DEBOUNCE_MS = 500;
            
            // Get or create debounce state for this demand ID
            if (!loadDemandDebounceMap.value.has(demandId)) {
                loadDemandDebounceMap.value.set(demandId, {
                    isLoading: false,
                    lastCallTime: 0,
                    queuedResolvers: [],
                    timeoutId: null
                });
            }
            
            const debounceState = loadDemandDebounceMap.value.get(demandId)!;
            
            // If already loading, queue this request
            if (debounceState.isLoading) {
                debounceState.queuedResolvers.push({ resolve, reject });
                return;
            }
            
            // If this is the first call or enough time has passed, execute immediately
            const timeSinceLastCall = now - debounceState.lastCallTime;
            if (debounceState.lastCallTime === 0 || timeSinceLastCall >= DEBOUNCE_MS) {
                executeLoadDemand(demandId, resolve, reject);
            } else {
                // Queue this request and set a timeout for the remaining debounce time
                debounceState.queuedResolvers.push({ resolve, reject });
                
                if (debounceState.timeoutId) {
                    clearTimeout(debounceState.timeoutId);
                }
                
                const remainingTime = DEBOUNCE_MS - timeSinceLastCall;
                debounceState.timeoutId = setTimeout(() => {
                    if (debounceState.queuedResolvers.length > 0) {
                        const { resolve: queuedResolve, reject: queuedReject } = debounceState.queuedResolvers.shift()!;
                        executeLoadDemand(demandId, queuedResolve, queuedReject);
                    }
                }, remainingTime);
            }
        });
    };

    // Execute the actual load and process queued requests
    const executeLoadDemand = async (
        demandId: number, 
        resolve: (value: any) => void, 
        reject: (error: any) => void
    ) => {
        const debounceState = loadDemandDebounceMap.value.get(demandId)!;
        
        // Mark as loading and update last call time
        debounceState.isLoading = true;
        debounceState.lastCallTime = Date.now();
        
        // Clear any pending timeout
        if (debounceState.timeoutId) {
            clearTimeout(debounceState.timeoutId);
            debounceState.timeoutId = null;
        }
        
        try {
            const result = await _loadDemandInternal(demandId);
            
            // Resolve the current request
            resolve(result);
            
            // Resolve all queued requests with the same result
            const queuedResolvers = [...debounceState.queuedResolvers];
            debounceState.queuedResolvers = [];
            queuedResolvers.forEach(({ resolve: queuedResolve }) => {
                queuedResolve(result);
            });
            
        } catch (error) {
            // Reject the current request
            reject(error);
            
            // Reject all queued requests with the same error
            const queuedResolvers = [...debounceState.queuedResolvers];
            debounceState.queuedResolvers = [];
            queuedResolvers.forEach(({ reject: queuedReject }) => {
                queuedReject(error);
            });
        } finally {
            // Mark as not loading
            debounceState.isLoading = false;
            
            // If there are still queued requests, process the next one after debounce
            if (debounceState.queuedResolvers.length > 0) {
                debounceState.timeoutId = setTimeout(() => {
                    if (debounceState.queuedResolvers.length > 0) {
                        const { resolve: nextResolve, reject: nextReject } = debounceState.queuedResolvers.shift()!;
                        executeLoadDemand(demandId, nextResolve, nextReject);
                    }
                }, 500);
            }
        }
    };


    // Helper function to subscribe to a single workflow run
    const subscribeToWorkflowRun = (workflowRun: WorkflowRun, demandId: number, onDemandUpdate?: (updatedDemand: UiDemand) => void) => {
        if (!pusher || !workflowRun?.id || subscribedWorkflowIds.value.has(workflowRun.id)) {
            return;
        }

        subscribedWorkflowIds.value.add(workflowRun.id);

        pusher.onModelEvent(
            workflowRun,
            "updated",
            async (updatedWorkflowRun: WorkflowRun) => {
                if (updatedWorkflowRun.status === "Completed") {
                    // Reload the demand to get updated data
                    const updatedDemand = await loadDemand(demandId);
                    if (onDemandUpdate) {
                        onDemandUpdate(updatedDemand);
                    }
                }
            }
        );
    };

    // Subscribe to workflow run updates for real-time status updates
    const subscribeToWorkflowRunUpdates = (demand: UiDemand, onDemandUpdate?: (updatedDemand: UiDemand) => void) => {
        if (!demand) return;

        // Subscribe to extract data workflow run
        if (demand.extract_data_workflow_run) {
            subscribeToWorkflowRun(demand.extract_data_workflow_run, demand.id, onDemandUpdate);
        }

        // Subscribe to write demand workflow run
        if (demand.write_demand_workflow_run) {
            subscribeToWorkflowRun(demand.write_demand_workflow_run, demand.id, onDemandUpdate);
        }
    };

    // Clear workflow subscriptions (useful when navigating away or changing demands)
    const clearWorkflowSubscriptions = () => {
        subscribedWorkflowIds.value.clear();
    };

    return {
        demands: sortedDemands,
        demandsByStatus,
        stats,
        isLoading,
        error,
        loadDemands,
        loadDemand,
        createDemand,
        updateDemand,
        deleteDemand,
        extractData,
        writeDemand,
        subscribeToWorkflowRunUpdates,
        clearWorkflowSubscriptions
    };
}
