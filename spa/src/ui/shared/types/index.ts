import type { StoredFile, User } from 'quasar-ui-danx';
import type { DEMAND_STATUS } from '../../../insurance-demands/config';
import type { WorkflowRun } from '../../../types';

export interface UiDemand {
  id: number;
  title: string;
  description?: string;
  status: typeof DEMAND_STATUS[keyof typeof DEMAND_STATUS];
  metadata?: any;
  completed_at?: string;
  created_at: string;
  updated_at: string;
  can_extract_data?: boolean;
  can_write_demand?: boolean;
  is_extract_data_running?: boolean;
  is_write_demand_running?: boolean;
  extract_data_workflow_run?: WorkflowRun;
  write_demand_workflow_run?: WorkflowRun;
  user?: User;
  files?: StoredFile[];
  files_count?: number;
}

export interface UiNavigation {
  title: string;
  icon: string;
  route: string;
  children?: UiNavigation[];
}

export interface UiTheme {
  name: string;
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    surface: string;
    text: string;
  };
}

export interface UiLayoutConfig {
  showSidebar: boolean;
  sidebarCollapsed: boolean;
  navigation: UiNavigation[];
  theme: UiTheme;
}