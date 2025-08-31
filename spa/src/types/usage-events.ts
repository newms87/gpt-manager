import { ActionTargetItem } from "quasar-ui-danx";

export interface UsageEvent extends ActionTargetItem {
    id: number;
    team_id: number;
    user_id: number | null;
    object_type: string;
    object_id: string;
    object_id_int: number | null;
    event_type: string;
    api_name: string | null;
    run_time_ms: number | null;
    input_tokens: number | null;
    output_tokens: number | null;
    input_cost: number | null;
    output_cost: number | null;
    request_count: number | null;
    data_volume: number | null;
    metadata: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Relationships
    user?: {
        id: number;
        name: string;
        email: string;
    };

    // Computed attributes
    total_cost?: number;
    total_tokens?: number;
}
