import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import { Agent, AgentThread } from "@/types/agents";
import { FragmentSelector, PromptSchema, PromptSchemaFragment } from "@/types/prompts";
import { WorkflowInput } from "@/types/workflow-inputs";
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
	taskInputs?: TaskInput[];
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

export interface TaskInput extends ActionTargetItem {
	id: string;
	taskDefinition: TaskDefinition;
	workflowInput: WorkflowInput;
	task_run_count: number;
	taskRuns: TaskRun[];
}

export interface TaskRun extends TaskRunner {
	id: number;
	process_count: number;
	job_dispatch_count: number;
	processes?: TaskProcess[];
}

export interface TaskProcess extends TaskRunner {
	id: number;
	created_at: string;
	input_artifact_count: number;
	output_artifact_count: number;
	job_dispatch_count: number;
	agentThread?: AgentThread;
	lastJobDispatch?: JobDispatch;
	jobDispatches?: JobDispatch[];
}

export interface TaskRunner extends ActionTargetItem {
	id: number;
	status: TaskRunStatus;
	started_at?: string;
	failed_at?: string;
	stopped_at?: string;
	completed_at?: string;
	timeout_at?: string;
	created_at: string;
	usage: UsageSummary;
}

export interface UsageSummary {
	count?: number;
	run_time_ms: number;
	input_tokens: number;
	output_tokens: number;
	input_cost: number;
	output_cost: number;
	total_cost: number;
}

export type TaskRunStatus = "Pending" | "Running" | "Failed" | "Completed" | "Stopped" | "Timeout";
