import { Agent, AgentThread } from "@/types/agents";
import { Artifact } from "@/types/artifacts";
import { WorkflowInput } from "@/types/workflow-inputs";
import { ActionTargetItem } from "quasar-ui-danx/types";

export interface Workflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	jobs?: WorkflowJob[];
	runs?: WorkflowRun[];
}

export interface WorkflowJobDependency {
	id: number;
	depends_on_id: number;
	depends_on_name: string;
	depends_on_workflow_tool: string;
	group_by: string;
}

export interface WorkflowJob extends ActionTargetItem {
	id: number;
	name: string;
	description: string;
	dependencies: WorkflowJobDependency[];
	assignments: WorkflowAssignment[];
	use_input: boolean;
	workflow_tool: string;
}

export interface WorkflowJobRun {
	id: number;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
	workflowJob: WorkflowJob;
	tasks: WorkflowTask[];
	usage: WorkflowUsage;
}

export interface WorkflowTask {
	id: number;
	job_name: string;
	group: string;
	agent_name: string;
	model: string;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
	artifact?: Artifact;
	thread: AgentThread;
	job_logs: string;
	usage: WorkflowUsage;
}

export interface WorkflowAssignment extends ActionTargetItem {
	id: number;
	agent: Agent;
	is_required: boolean;
	max_attempts: number;
	group_by: string;
}

export interface WorkflowRun extends ActionTargetItem {
	id: number;
	workflow_id: number;
	workflow_name: string;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
	workflowInput?: WorkflowInput;
	workflowJobRuns?: WorkflowJobRun[];
	artifacts?: Artifact[];
	usage: WorkflowUsage;
}

export interface WorkflowUsage {
	input_tokens: number;
	output_tokens: number;
	cost: number;
}
