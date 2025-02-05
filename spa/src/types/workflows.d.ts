import { Agent, AgentThread } from "@/types/agents";
import { Artifact } from "@/types/artifacts";
import { PromptSchema } from "@/types/prompts";
import { TaskRunner } from "@/types/task-definitions";
import { WorkflowInput } from "@/types/workflow-inputs";
import { ActionTargetItem, AnyObject, ListControlsRoutes } from "quasar-ui-danx";

export interface Workflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	jobs?: WorkflowJob[];
	runs?: WorkflowRun[];
}

export interface OrderByDependency {
	name: string;
	direction: "asc" | "desc";
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
	order_by?: OrderByDependency;
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
	responseSchema?: PromptSchema | null;
}

export interface WorkflowRun extends TaskRunner {
	workflow_id: number;
	workflow_name: string;
	input_id: number;
	input_name: string;
	artifacts_count: number;
	job_runs_count: number;
	workflowInput?: WorkflowInput;
	workflowJobRuns?: WorkflowJobRun[];
	artifacts?: Artifact[];
}

export interface WorkflowJobRun extends TaskRunner {
	name: string;
	workflowJob: WorkflowJob;
	tasks: WorkflowTask[];
}

export interface WorkflowTask extends TaskRunner {
	job_name: string;
	group: string;
	agent_id: number;
	agent_name: string;
	model: string;
	artifact?: Artifact;
	thread: AgentThread;
	audit_request_id: string;
	logs?: string;
}

export interface WorkflowAssignment extends ActionTargetItem {
	id: number;
	is_required: boolean;
	max_attempts: number;
	group_by: string;
	workflowJob?: WorkflowJob;
	agent?: Agent;
}

export interface WorkflowRunStatuses {
	total_count: number;
	completed_count: number;
	failed_count: number;
	pending_count: number;
	running_count: number;
}

export interface WorkflowRunRoutes extends ListControlsRoutes<WorkflowRun> {
	runStatuses(filter: AnyObject): Promise<WorkflowRunStatuses>;
}
