import { SelectionSchema } from "@/types/json-schema";
import { AgentPromptDirective, PromptSchema } from "@/types/prompts";
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
	response_sub_selection?: SelectionSchema;
	directives?: AgentPromptDirective[];
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
