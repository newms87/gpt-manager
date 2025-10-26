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
    request: string;
    response: string;
    request_headers: AnyObject;
    response_headers: AnyObject;
    run_time_ms: number;
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
