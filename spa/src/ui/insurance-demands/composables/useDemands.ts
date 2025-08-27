import { FlashMessages, storeObject, storeObjects } from "quasar-ui-danx";
import { computed, ref } from "vue";
import type { UiDemand } from "../../shared/types";
import { DEMAND_STATUS, demandRoutes } from "../config";

const demands = ref<UiDemand[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);

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
            demands.value = storeObjects(response.data);
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
            const updatedDemand = response.item;
            const index = demands.value.findIndex(d => d.id === id);
            if (index !== -1) {
                demands.value[index] = updatedDemand;
            }
            return updatedDemand;
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


    const extractData = async (idOrDemand: number | UiDemand) => {
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

            // Check if response is an error (has error or message fields indicating failure)
            if (response?.error || response?.message?.includes("Failed")) {
                const errorMessage = response?.error || response?.message || "Failed to extract data";
                FlashMessages.error(errorMessage);
                throw new Error(errorMessage);
            }

            const updatedDemand = storeObject(response);

            // Update the demand in the array if it exists
            const index = demands.value.findIndex(d => d.id === demand.id);
            if (index !== -1) {
                demands.value[index] = updatedDemand;
            }

            return updatedDemand;
        } catch (err: any) {
            const errorMessage = err?.response?.data?.error || err?.response?.data?.message || err.message || "Failed to extract data";
            error.value = errorMessage;
            FlashMessages.error(errorMessage);

            // Don't update the demand object with error response - just throw the error
            throw err;
        }
    };

    const writeDemand = async (idOrDemand: number | UiDemand, templateId?: string, additionalInstructions?: string) => {
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

            const updatedDemand = storeObject(response);

            // Update the demand in the array if it exists
            const index = demands.value.findIndex(d => d.id === demand.id);
            if (index !== -1) {
                demands.value[index] = updatedDemand;
            }

            return updatedDemand;
        } catch (err: any) {
            const errorMessage = err?.response?.data?.error || err?.response?.data?.message || err.message || "Failed to write demand";
            error.value = errorMessage;
            FlashMessages.error(errorMessage);

            // Don't update the demand object with error response - just throw the error
            throw err;
        }
    };


    return {
        demands: sortedDemands,
        demandsByStatus,
        stats,
        isLoading,
        error,
        loadDemands,
        createDemand,
        updateDemand,
        deleteDemand,
        extractData,
        writeDemand
    };
}
