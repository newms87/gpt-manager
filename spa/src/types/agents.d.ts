import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import { AgentPromptDirective, SchemaDefinition, SchemaFragment } from "@/types";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Agent extends ActionTargetItem {
	id: string;
	name: string;
	model: string;
	temperature: string;
	description: string;
	threads_count: number;
	tools: string[];
	threads: AgentThread[];
	directives?: AgentPromptDirective[];
}

export interface AgentThread extends ActionTargetItem {
	id: number;
	name: string;
	summary: string;
	messages: AgentThreadMessage[];
	is_running: boolean;
	timestamp: string;
	jobDispatch?: JobDispatch;
	can: {
		view: boolean;
		edit: boolean;
	};
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

export interface AgentThreadResponseFormat {
	format: "text" | "json_schema";
	schema?: SchemaDefinition;
	fragment?: SchemaFragment;
}
