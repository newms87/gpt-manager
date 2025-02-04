import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import { Agent, AgentThread } from "@/types/agents";
import { FragmentSelector, PromptSchema, PromptSchemaFragment } from "@/types/prompts";
import { ActionTargetItem } from "quasar-ui-danx";

export interface TaskDefinition extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	task_runner_class: string;
	input_grouping?: FragmentSelector[];
	input_group_chunk_size: number;
	timeout_after_seconds: number;
	task_run_count: number;
	task_agent_count: number;
	taskRuns?: TaskRun[];
	taskAgents?: TaskDefinitionAgent[];
}

export interface TaskDefinitionAgent extends ActionTargetItem {
	id: string;
	agent: Agent;
	include_text: boolean;
	include_files: boolean;
	include_data: boolean;
	inputSchema?: PromptSchema;
	inputSchemaFragment?: PromptSchemaFragment;
	outputSchema?: PromptSchema;
	outputSchemaFragment?: PromptSchemaFragment;
}

export interface TaskRun extends ActionTargetItem {
	id: number;
	status: string;
	started_at?: string;
	failed_at?: string;
	stopped_at?: string;
	completed_at?: string;
	input_tokens: number;
	output_tokens: number;
	process_count: number;
	processes: TaskRunProcess;
}

export interface TaskRunProcess extends ActionTargetItem {
	id: number;
	status: string;
	started_at?: string;
	failed_at?: string;
	stopped_at?: string;
	completed_at?: string;
	timeout_at?: string;
	input_tokens: number;
	output_tokens: number;
	created_at: string;
	agentThread?: AgentThread;
	lastJobDispatch?: JobDispatch;
	jobDispatches?: JobDispatch[];
}
