// Assistant Action Types
export interface AssistantAction {
	id: number;
	context: string;
	action_type: string;
	target_type: string;
	target_id?: string;
	status: "pending" | "in_progress" | "completed" | "failed" | "cancelled";
	title: string;
	description?: string;
	payload?: Record<string, any>;
	preview_data?: Record<string, any>;
	result_data?: Record<string, any>;
	error_message?: string;
	duration?: number;
	started_at?: string;
	completed_at?: string;
	created_at: string;
	updated_at: string;

	// Helper properties
	is_pending: boolean;
	is_in_progress: boolean;
	is_completed: boolean;
	is_failed: boolean;
	is_cancelled: boolean;
	is_finished: boolean;
}

// Thread and Message Types
export interface AssistantThread {
	id: number;
	name: string;
	description?: string;
	is_running?: boolean;
	messages?: AssistantMessage[];
	runs?: AssistantThreadRun[];
	created_at: string;
	updated_at: string;
}

export interface AssistantMessage {
	id: number | string;
	thread_id?: number;
	role: "user" | "assistant" | "error";
	content: string;
	data?: Record<string, any>;
	error_data?: any;
	created_at: string;
	updated_at?: string;
}

export interface AssistantThreadRun {
	id: number;
	thread_id: number;
	status: "running" | "completed" | "stopped" | "failed";
	model?: string;
	temperature?: number;
	max_tokens?: number;
	created_at: string;
	updated_at: string;
}

// Context Types
export interface AssistantContext {
	name: string;
	displayName: string;
	capabilities: AssistantCapability[];
	routes: string[];
}

export interface AssistantCapability {
	key: string;
	label: string;
	description?: string;
	icon?: string;
}

// API Response Types
export interface ChatResponse {
	thread: AssistantThread;
	message?: AssistantMessage;
	actions?: AssistantAction[];
	context_capabilities?: AssistantCapability[];
}

// API response is now just the AssistantAction directly
export type ActionResponse = AssistantAction;

// API response is now just the capabilities object directly
export type CapabilitiesResponse = Record<string, string>;

// Schema-specific types
export interface SchemaModificationPreview {
	modification_type: "add_property" | "modify_property" | "remove_property" | "restructure";
	target_path: string;
	modification_data: Record<string, any>;
	reason: string;
	before_schema?: Record<string, any>;
	after_schema?: Record<string, any>;
}

// Context Data Types
export interface SchemaEditorContextData {
	route_path: string;
	route_name?: string;
	route_params: Record<string, any>;
	route_query: Record<string, any>;
	schema_id?: string | number;
	editing_schema?: boolean;
}

export interface WorkflowEditorContextData {
	route_path: string;
	route_name?: string;
	route_params: Record<string, any>;
	route_query: Record<string, any>;
	workflow_id?: string | number;
	designing_workflow?: boolean;
}

export interface AgentManagementContextData {
	route_path: string;
	route_name?: string;
	route_params: Record<string, any>;
	route_query: Record<string, any>;
	agent_id?: string | number;
	configuring_agent?: boolean;
}

export interface TaskManagementContextData {
	route_path: string;
	route_name?: string;
	route_params: Record<string, any>;
	route_query: Record<string, any>;
	task_id?: string | number;
	editing_task?: boolean;
}

export type ContextData =
		| SchemaEditorContextData
		| WorkflowEditorContextData
		| AgentManagementContextData
		| TaskManagementContextData
		| Record<string, any>;

// Event Types
export interface AssistantEvent {
	type: "message" | "action" | "context_change" | "error";
	data: any;
	timestamp: string;
}

// Component Props Types
export interface AssistantChatProps {
	thread?: AssistantThread | null;
	context: string;
	contextData?: Record<string, any>;
	loading?: boolean;
}

export interface AssistantActionPanelProps {
	actions: AssistantAction[];
	threadId?: number;
	collapsible?: boolean;
	defaultCollapsed?: boolean;
}

export interface AssistantActionBoxProps {
	action: AssistantAction;
}

export interface AssistantActionPreviewProps {
	action: AssistantAction;
	visible?: boolean;
	loading?: boolean;
}

// Store Types (if using a dedicated store)
export interface AssistantStore {
	isOpen: boolean;
	currentThread: AssistantThread | null;
	activeActions: AssistantAction[];
	context: string;
	contextData: Record<string, any>;
	capabilities: AssistantCapability[];
	unreadCount: number;
	loading: boolean;
}

// Error Types
export interface AssistantError {
	code: string;
	message: string;
	details?: Record<string, any>;
	timestamp: string;
}

// Configuration Types
export interface AssistantConfig {
	autoOpen: boolean;
	persistent: boolean;
	minimizable: boolean;
	defaultContext: string;
	enableContextDetection: boolean;
	maxActiveActions: number;
	actionTimeout: number;
}
