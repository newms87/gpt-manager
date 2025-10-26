import { apiUrls } from '@/api';
import { useActionRoutes, request } from 'quasar-ui-danx';
import type { UiDemand } from '../../shared/types';

export const demandRoutes = useActionRoutes(apiUrls.demands.uiDemands, {
  extractData: async (demand: UiDemand) => request.post(`${apiUrls.demands.uiDemands}/${demand.id}/extract-data`),
  writeMedicalSummary: async (demand: UiDemand, data?: any) => request.post(`${apiUrls.demands.uiDemands}/${demand.id}/write-medical-summary`, data || {}),
  writeDemandLetter: async (demand: UiDemand, data?: any) => request.post(`${apiUrls.demands.uiDemands}/${demand.id}/write-demand-letter`, data || {})
});

// Status constants matching backend UiDemand::STATUS_* constants
export const DEMAND_STATUS = {
  DRAFT: 'Draft',
  COMPLETED: 'Completed',
  FAILED: 'Failed',
} as const;

export const demandStatuses = [
  { value: DEMAND_STATUS.DRAFT, label: DEMAND_STATUS.DRAFT, color: 'slate' },
  { value: DEMAND_STATUS.COMPLETED, label: DEMAND_STATUS.COMPLETED, color: 'green' },
  { value: DEMAND_STATUS.FAILED, label: DEMAND_STATUS.FAILED, color: 'red' },
] as const;

// Status color mapping function
export function getDemandStatusColor(status: string): string {
  const statusConfig = demandStatuses.find(s => s.value === status);
  return statusConfig?.color || 'slate';
}

// Progress percentage calculation based on status
export function getDemandProgressPercentage(status: string): number {
  switch (status) {
    case DEMAND_STATUS.DRAFT:
      return 0;
    case DEMAND_STATUS.COMPLETED:
      return 100;
    case DEMAND_STATUS.FAILED:
      return 0;
    default:
      return 0;
  }
}