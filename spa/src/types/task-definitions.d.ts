import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import {
	Agent,
	AgentThread,
	Artifact,
	PromptDirective,
	SchemaAssociation,
	SchemaDefinition,
	SchemaFragment,
	WorkflowInput
} from "@/types";
import { ActionTargetItem, AnyObject } from "quasar-ui-danx";

export interface TaskDefinition extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	is_trigger: boolean;
	task_runner_class: string;
	task_runner_config?: AnyObject;
	artifact_split_mode: ArtifactSplitMode;
	timeout_after_seconds: number;
	task_run_count: number;
	task_agent_count: number;
	taskRuns?: TaskRun[];
	taskInputs?: TaskInput[];
	taskArtifactFiltersAsTarget: TaskArtifactFilter[];
	agent?: Agent;
	schemaDefinition?: SchemaDefinition;
	schemaAssociations?: SchemaAssociation[];
	taskDefinitionDirectives?: TaskDefinitionDirective[];
}

export interface TaskDefinitionDirective extends ActionTargetItem {
	id: string;
	directive: PromptDirective;
	position: number;
	section: string;
}

export interface TaskArtifactFilter extends ActionTargetItem {
	source_task_definition_id: string;
	target_task_definition_id: string;
	include_text: boolean;
	include_files: boolean;
	include_json: boolean;
	schema_fragment_id?: string;
	schemaFragment?: SchemaFragment;
}

export type ArtifactSplitMode = "" | "Node" | "Artifact";

export interface TaskInput extends ActionTargetItem {
	id: string;
	taskDefinition: TaskDefinition;
	workflowInput: WorkflowInput;
	task_run_count: number;
	taskRuns: TaskRun[];
}

export interface TaskRun extends TaskRunner {
	id: number;
	step: string;
	percent_complete: number;
	process_count: number;
	job_dispatch_count: number;
	processes?: TaskProcess[];
	task_definition_id: number;
	taskDefinition?: TaskDefinition;
	workflow_node_id: number;
	input_artifacts_count: number;
	output_artifacts_count: number;
	inputArtifacts?: Artifact[];
	outputArtifacts?: Artifact[];
}

export interface TaskProcess extends TaskRunner {
	id: number;
	activity: string;
	percent_complete: number;
	created_at: string;
	input_artifact_count: number;
	output_artifact_count: number;
	job_dispatch_count: number;
	agentThread?: AgentThread;
	lastJobDispatch?: JobDispatch;
	jobDispatches?: JobDispatch[];
	inputArtifacts?: Artifact[];
	outputArtifacts?: Artifact[];
	taskRun?: TaskRun;
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
