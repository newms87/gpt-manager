import { useActionRoutes } from 'quasar-ui-danx';
import type { UiDemand } from '../../shared/types';

const API_URL = import.meta.env.VITE_API_URL;

export const demandRoutes = useActionRoutes(API_URL + "/ui-demands");

// Status constants matching backend UiDemand::STATUS_* constants
export const DEMAND_STATUS = {
  DRAFT: 'Draft',
  READY: 'Ready', 
  PROCESSING: 'Processing',
  COMPLETED: 'Completed',
  FAILED: 'Failed',
} as const;

export const demandStatuses = [
  { value: DEMAND_STATUS.DRAFT, label: DEMAND_STATUS.DRAFT, color: 'slate' },
  { value: DEMAND_STATUS.READY, label: DEMAND_STATUS.READY, color: 'blue' },
  { value: DEMAND_STATUS.PROCESSING, label: DEMAND_STATUS.PROCESSING, color: 'amber' },
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
    case DEMAND_STATUS.READY:
      return 25;
    case DEMAND_STATUS.PROCESSING:
      return 50;
    case DEMAND_STATUS.COMPLETED:
      return 100;
    case DEMAND_STATUS.FAILED:
      return 0;
    default:
      return 0;
  }
}