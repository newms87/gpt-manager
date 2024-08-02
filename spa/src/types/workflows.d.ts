import { Agent, AgentThread } from "@/types/agents";
import { Artifact } from "@/types/artifacts";
import { WorkflowInput } from "@/types/workflow-inputs";
import { ActionTargetItem, AnyObject } from "quasar-ui-danx";

export interface Workflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	jobs?: WorkflowJob[];
	runs?: WorkflowRun[];
}

export interface OrderByDependency {
	name: string;
	order: "asc" | "desc";
}

export interface WorkflowJobDependency {
	id: number;
	depends_on_id: number;
	depends_on_name: string;
	depends_on_workflow_tool: string;
	depends_on_fields: string[];
	force_schema?: boolean;
	include_fields?: string[];
	group_by?: string[];
	order_by?: OrderByDependency[];
}

export interface WorkflowJob extends ActionTargetItem {
	id: number;
	name: string;
	description: string;
	workflow_tool: string;
	tasks_preview: AnyObject | null;
	dependencies: WorkflowJobDependency[];
	assignments: WorkflowAssignment[];
	workflow?: Workflow;
}

export interface WorkflowJobRun {
	id: number;
	name: string;
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
	audit_request_id: string;
	logs?: string;
	usage: WorkflowUsage;
}

export interface WorkflowAssignment extends ActionTargetItem {
	id: number;
	is_required: boolean;
	max_attempts: number;
	group_by: string;
	workflowJob?: WorkflowJob;
	agent?: Agent;
}

export interface WorkflowRun extends ActionTargetItem {
	id: number;
	workflow_id: number;
	workflow_run_name: string;
	input_name: string;
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
	count?: number;
	input_tokens: number;
	output_tokens: number;
	total_cost: number;
}
