import { ApiLog, JobDispatch } from "@/components/Modules/Audits/audit-requests";
import { AgentPromptDirective, SchemaDefinition, SchemaFragment } from "@/types";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Agent extends ActionTargetItem {
	id: string;
	name: string;
	model: string;
	description: string;
	threads_count: number;
	threads: AgentThread[];
	directives?: AgentPromptDirective[];
	api_options?: AgentApiOptions;
}

export interface AgentApiOptions {
	temperature?: number;
	model?: string;
	reasoning?: {
		effort?: "low" | "medium" | "high";
		summary?: "auto" | "detailed" | null;
	};
	service_tier?: "auto" | "default" | "flex";
	stream?: boolean;
}

export interface AgentThread extends ActionTargetItem {
	id: number;
	name: string;
	summary: string;
	messages: AgentThreadMessage[];
	chat_messages?: AgentThreadMessage[];
	is_running: boolean;
	timestamp: string;
	jobDispatch?: JobDispatch;
	can: {
		view: boolean;
		edit: boolean;
	};
}

export interface AgentThreadRun extends ActionTargetItem {
	agent_thread_id: number;
	status: string;
	started_at: string;
	completed_at: string;
	failed_at: string;
	refreshed_at: string;
}

export interface AgentThreadMessage extends ActionTargetItem {
	id: number;
	role: "assistant" | "user";
	title: string;
	summary?: string;
	content?: string;
	data?: AnyObject;
	files?: UploadedFile[];
	timestamp: string;
	api_response_id?: string;
	api_log_id?: string | number | null;
	apiLog?: ApiLog;
}

export interface AgentThreadResponseFormat {
	format: AgentResponseFormat;
	schema?: SchemaDefinition;
	fragment?: SchemaFragment;
}

export type AgentResponseFormat = "text" | "json_schema";
