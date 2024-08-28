import { AgentPromptDirective, PromptSchema } from "@/types/prompts";
import { WorkflowAssignment } from "@/types/workflows";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Agent extends ActionTargetItem {
	id: string;
	name: string;
	model: string;
	temperature: string;
	description: string;
	prompt: string;
	threads_count: number;
	assignments_count: number;
	tools: string[];
	threads: AgentThread[];
	assignments?: WorkflowAssignment[];
	response_format: "text" | "json_object" | "json_schema";
	response_notes?: string;
	response_schema?: PromptSchema;
	directives?: AgentPromptDirective[];
	response_sample?: AnyObject;
}

export interface AgentThread extends ActionTargetItem {
	id: number;
	name: string;
	summary: string;
	messages: ThreadMessage[];
	is_running: boolean;
	timestamp: string;
}

export interface ThreadMessage extends ActionTargetItem {
	id: number;
	role: "assistant" | "user" | "tool";
	title: string;
	summary?: string;
	content?: string;
	data?: AnyObject;
	files?: UploadedFile[];
	timestamp: string;
}
