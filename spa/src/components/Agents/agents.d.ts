import { ActionTargetItem } from "quasar-ui-danx/types";

export interface Agent extends ActionTargetItem {
	id: string;
	name: string;
	model: string;
	temperature: string;
	description: string;
	prompt: string;
	threads: AgentThread[];
}

export interface AgentThread extends ActionTargetItem {
	id: number;
	name: string;
	summary: string;
	messages: ThreadMessage[];
}

export interface ThreadMessage extends ActionTargetItem {
	id: number;
	role: "assistant" | "user";
	title: string;
	content: string;
}
