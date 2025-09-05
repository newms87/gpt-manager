import { ActionTargetItem, StoredFile, User } from "quasar-ui-danx";
import type { TeamObject } from "../../../components/Modules/TeamObjects/team-objects";
import type { DEMAND_STATUS } from "../../../insurance-demands/config";
import type { WorkflowRun } from "../../../types";
import type { UsageEvent, UsageSummary } from "./usage";

export interface Artifact {
    id: number;
    original_artifact_id?: number;
    name: string;
    position?: number;
    model?: string;
    created_at: string;
    child_artifacts_count?: number;
    text_content?: string;
    json_content?: any;
    files?: any[];
    meta?: any;
    task_process_id?: number;
}

export interface UiDemand extends ActionTargetItem {
    id: number;
    title: string;
    description?: string;
    status: typeof DEMAND_STATUS[keyof typeof DEMAND_STATUS];
    metadata?: any;
    completed_at?: string;
    created_at: string;
    updated_at: string;
    can_extract_data?: boolean;
    can_write_medical_summary?: boolean;
    can_write_demand_letter?: boolean;
    is_extract_data_running?: boolean;
    is_write_medical_summary_running?: boolean;
    is_write_demand_letter_running?: boolean;
    extract_data_workflow_run?: WorkflowRun;
    write_medical_summary_workflow_run?: WorkflowRun;
    write_demand_letter_workflow_run?: WorkflowRun;
    user?: User;
    input_files?: StoredFile[];
    output_files?: StoredFile[];
    medical_summaries?: Artifact[];
    input_files_count?: number;
    output_files_count?: number;
    medical_summaries_count?: number;
    usage_summary?: UsageSummary | null;
    team_object?: TeamObject | null;
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

// Re-export usage types
export type { UsageSummary, UsageEvent } from "./usage";
