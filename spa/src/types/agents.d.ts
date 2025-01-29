import { AgentPromptDirective, PromptSchema, PromptSchemaFragment } from "@/types/prompts";
import { WorkflowAssignment } from "@/types/workflows";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Agent extends ActionTargetItem {
	id: string;
	name: string;
	model: string;
	temperature: string;
	description: string;
	threads_count: number;
	assignments_count: number;
	tools: string[];
	threads: AgentThread[];
	assignments?: WorkflowAssignment[];
	response_format: "text" | "json_object" | "json_schema";
	responseSchema?: PromptSchema;
	responseSchemaFragment?: PromptSchemaFragment;
	directives?: AgentPromptDirective[];
}

export interface AgentThread extends ActionTargetItem {
	id: number;
	name: string;
	summary: string;
	messages: AgentThreadMessage[];
	is_running: boolean;
	timestamp: string;
}

export interface AgentThreadMessage extends ActionTargetItem {
	id: number;
	role: "assistant" | "user" | "tool";
	title: string;
	summary?: string;
	content?: string;
	data?: AnyObject;
	files?: UploadedFile[];
	timestamp: string;
}
