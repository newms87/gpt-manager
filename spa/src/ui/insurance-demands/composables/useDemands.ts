import { ref, computed } from 'vue';
import { storeObjects, storeObject } from 'quasar-ui-danx';
import { demandRoutes, DEMAND_STATUS } from '../config';
import type { UiDemand } from '../../shared/types';

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
    ready: demands.value.filter(d => d.status === DEMAND_STATUS.READY).length,
    processing: demands.value.filter(d => d.status === DEMAND_STATUS.PROCESSING).length,
    completed: demands.value.filter(d => d.status === DEMAND_STATUS.COMPLETED).length,
    failed: demands.value.filter(d => d.status === DEMAND_STATUS.FAILED).length,
  }));

  const loadDemands = async () => {
    try {
      isLoading.value = true;
      error.value = null;
      const response = await demandRoutes.list();
      demands.value = storeObjects(response.data);
    } catch (err: any) {
      error.value = err.message || 'Failed to load demands';
      console.error('Error loading demands:', err);
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
      error.value = err.message || 'Failed to update demand';
      throw err;
    }
  };

  const deleteDemand = async (id: number) => {
    try {
      await demandRoutes.applyAction("delete", { id });
      demands.value = demands.value.filter(d => d.id !== id);
    } catch (err: any) {
      error.value = err.message || 'Failed to delete demand';
      throw err;
    }
  };

  const submitDemand = async (id: number) => {
    try {
      const response = await demandRoutes.applyAction("submit", { id });
      const updatedDemand = response.item;
      const index = demands.value.findIndex(d => d.id === id);
      if (index !== -1) {
        demands.value[index] = updatedDemand;
      }
      return updatedDemand;
    } catch (err: any) {
      error.value = err.message || 'Failed to submit demand';
      throw err;
    }
  };

  const extractData = async (id: number) => {
    try {
      const response = await demandRoutes.applyAction("extract-data", { id });
      const updatedDemand = response.item;
      const index = demands.value.findIndex(d => d.id === id);
      if (index !== -1) {
        demands.value[index] = updatedDemand;
      }
      return updatedDemand;
    } catch (err: any) {
      error.value = err.message || 'Failed to extract data';
      throw err;
    }
  };

  const writeDemand = async (id: number) => {
    try {
      const response = await demandRoutes.applyAction("write-demand", { id });
      const updatedDemand = response.item;
      const index = demands.value.findIndex(d => d.id === id);
      if (index !== -1) {
        demands.value[index] = updatedDemand;
      }
      return updatedDemand;
    } catch (err: any) {
      error.value = err.message || 'Failed to write demand';
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
    submitDemand,
    extractData,
    writeDemand,
  };
}