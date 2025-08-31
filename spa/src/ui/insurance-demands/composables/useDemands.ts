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


    const extractData = async (idOrDemand: number | UiDemand, onDemandUpdate?: (updatedDemand: UiDemand) => void) => {
        try {
            let demand: UiDemand;
            if (typeof idOrDemand === "number") {
                const foundDemand = demands.value.find(d => d.id === idOrDemand);
                if (!foundDemand) {
                    throw new Error("Demand not found");
                }
                demand = foundDemand;
            } else {
                demand = idOrDemand;
            }

            const response = await demandRoutes.extractData(demand);

            storeObject(response.data);

            // Check if response is an error (has error or message fields indicating failure)
            if (response?.error || response?.message?.includes("Failed")) {
                const errorMessage = response?.error || response?.message || "Failed to extract data";
                FlashMessages.error(errorMessage);
                throw new Error(errorMessage);
            }

            // Subscribe to workflow run updates after starting extract data
            subscribeToWorkflowRunUpdates(demand, onDemandUpdate);

            return demand;
        } catch (err: any) {
            const errorMessage = err?.response?.data?.error || err?.response?.data?.message || err.message || "Failed to extract data";
            error.value = errorMessage;
            FlashMessages.error(errorMessage);

            // Don't update the demand object with error response - just throw the error
            throw err;
        }
    };

    const writeDemand = async (idOrDemand: number | UiDemand, templateId?: string, additionalInstructions?: string, onDemandUpdate?: (updatedDemand: UiDemand) => void) => {
        try {
            let demand: UiDemand;
            if (typeof idOrDemand === "number") {
                const foundDemand = demands.value.find(d => d.id === idOrDemand);
                if (!foundDemand) {
                    throw new Error("Demand not found");
                }
                demand = foundDemand;
            } else {
                demand = idOrDemand;
            }

            const data: any = {};
            if (templateId) {
                data.template_id = templateId;
            }
            if (additionalInstructions) {
                data.additional_instructions = additionalInstructions;
            }

            const response = await demandRoutes.writeDemand(demand, data);

            // Check if response is an error (has error or message fields indicating failure)
            if (response?.error || response?.message?.includes("Failed")) {
                const errorMessage = response?.error || response?.message || "Failed to write demand";
                FlashMessages.error(errorMessage);
                throw new Error(errorMessage);
            }

            // Subscribe to workflow run updates after starting write demand
            subscribeToWorkflowRunUpdates(demand, onDemandUpdate);

            return demand;
        } catch (err: any) {
            const errorMessage = err?.response?.data?.error || err?.response?.data?.message || err.message || "Failed to write demand";
            error.value = errorMessage;
            FlashMessages.error(errorMessage);

            // Don't update the demand object with error response - just throw the error
            throw err;
        }
    };


    // Load a single demand by ID with basic relationships
    const loadDemand = async (demandId: number) => {
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
