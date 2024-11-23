import { Agent } from "@/types/agents";
import { JsonSchema } from "@/types/json-schema";
import { ActionTargetItem } from "quasar-ui-danx";

export interface PromptSchema extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	agents_count: number;
	workflow_jobs_count: number;
	schema_format: "text" | "json" | "yaml" | "ts";
	schema: JsonSchema;
	response_example: object | object[];
	agents: Agent[];
}

export interface PromptDirective extends ActionTargetItem {
	id: string;
	name: string;
	directive_text: string;
	agents_count: number;
}

export interface AgentPromptDirective extends ActionTargetItem {
	id: string;
	directive: PromptDirective;
	position: number;
	section: string;
}
