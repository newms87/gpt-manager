import { Agent } from "@/types/agents";
import { ActionTargetItem } from "quasar-ui-danx";

export interface PromptSchema extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	agents_count: number;
	workflow_jobs_count: number;
	schema_format: "text" | "json" | "yaml" | "ts";
	schema: object | object[];
	agents: Agent[];
}
