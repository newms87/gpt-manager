import { ActionTargetItem, AnyObject } from "quasar-ui-danx";

export interface Audit {
    id: string;
    event: string;
    auditable_title: string;
    old_values: AnyObject;
    new_values: AnyObject;
    created_at: string;
}

export interface ApiLog {
    id: string;
    api_class: string;
    service_name: string;
    status_code: number;
    method: string;
    url: string;
    request: string | AnyObject;
    response: string | AnyObject;
    request_headers: AnyObject;
    response_headers: AnyObject;
    run_time_ms: number | string;
    started_at?: string;
    finished_at?: string;
    created_at: string;
}

export interface JobDispatch {
    id: string;
    name: string;
    ref: string;
    job_batch_id: string;
    running_audit_request_id: string;
    dispatch_audit_request_id: string;
    status: string;
    ran_at: string;
    completed_at: string;
    timeout_at: string;
    run_time_ms: string;
    count: string;
    created_at: string;

    api_log_count: number;
    error_log_count: number;
    log_line_count: number;

    logs?: string;
    errors?: ErrorLogEntry[];
    apiLogs?: ApiLog[];
}

export interface ErrorLogEntry {
    id: string;
    audit_request_id: string | null;
    error_class: string;
    code: string;
    level: string;
    last_seen_at: string;
    file: string;
    line: string;
    message: string;
    data: string;
    stack_trace: StackTraceEntry[];
    created_at: string;
}

export interface StackTraceEntry {
    file: string;
    line: string;
    function: string;
    class: string;
    type: string;
}

export interface AuditRequest extends ActionTargetItem {
    id: string;
    session_id: string;
    user_name: string;
    environment: string;
    url: string;
    request: AnyObject;
    response: AnyObject;
    logs: string;
    time: number;
    audits: Audit[];
    api_logs: ApiLog[];
    ran_jobs: JobDispatch[];
    dispatched_jobs: JobDispatch[];
    errors: ErrorLogEntry[];
    created_at: string;
    updated_at: string;
}

// OpenAI API Types
export interface OpenAiInputContent {
    type: "input_text" | "input_image";
    text?: string;
    image_url?: string | { url: string };
}

export interface OpenAiInputMessage {
    role: "user" | "assistant" | "system";
    content: OpenAiInputContent[];
}

export interface OpenAiRequestData {
    model?: string;
    instructions?: string;
    input?: string | OpenAiInputMessage[];
    reasoning?: { effort?: string };
    service_tier?: string;
    text?: {
        format?: {
            type: string;
            name?: string;
            schema?: AnyObject;
        };
    };
}

export interface OpenAiOutputContent {
    type: "output_text";
    text: string;
}

export interface OpenAiOutputItem {
    type: "reasoning" | "message";
    summary?: string[];
    content?: OpenAiOutputContent[];
}

export interface OpenAiResponseData {
    status?: "completed" | "failed" | string;
    error_type?: string;
    error_message?: string;
    usage?: {
        input_tokens: number;
        output_tokens: number;
        total_tokens: number;
        input_tokens_details?: { cached_tokens?: number };
        output_tokens_details?: { reasoning_tokens?: number };
    };
    output?: OpenAiOutputItem[];
}
