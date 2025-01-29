import { Agent } from "@/types/agents";
import { JsonSchema, JsonSchemaType } from "@/types/json-schema";
import { ActionTargetItem, ListControlsRoutes } from "quasar-ui-danx";

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

export interface PromptSchemaFragment extends ActionTargetItem {
	id: string;
	name: string;
	fragment_selector: FragmentSelector;
}

export interface FragmentSelector {
	type: JsonSchemaType;
	children?: {
		[key: string]: FragmentSelector;
	};
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

export interface PromptSchemaRevision {
	id: number;
	schema: PromptSchema;
	user_email: string;
	created_at: string;
}

export interface PromptSchemaRoutes extends ListControlsRoutes<PromptSchema> {
	history(target: PromptSchema): Promise<PromptSchemaRevision[]>;
}
