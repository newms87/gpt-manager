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

export interface WorkflowConfig {
    key: string;
    name: string;
    label: string;
    description: string;
    color: string;
    extracts_data: boolean;
    depends_on: string[];
    input: {
        source: 'demand' | 'team_object';
        requires_input_files?: boolean;
        include_artifacts_from?: Array<{
            workflow: string;
            category: string;
        }>;
    };
    template_categories: string[];
    instruction_categories: string[];
    display_artifacts?: {
        section_title: string;
        artifact_category: string;
        display_type?: 'artifacts' | 'files';
        editable?: boolean;
        deletable?: boolean;
    } | false;
}

export interface ArtifactSection {
    workflow_key: string;
    section_title: string;
    artifact_category: string;
    display_type: 'artifacts' | 'files';
    editable: boolean;
    deletable: boolean;
    artifacts: Artifact[];
    color: string;
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
    workflow_runs: Record<string, WorkflowRun[]>;
    workflow_config: WorkflowConfig[];
    artifact_sections: ArtifactSection[];
    user?: User;
    input_files?: StoredFile[];
    output_files?: StoredFile[];
    input_files_count?: number;
    output_files_count?: number;
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
