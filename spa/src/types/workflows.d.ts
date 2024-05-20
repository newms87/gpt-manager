import { Agent, AgentThread } from "@/types/agents";
import { Artifact } from "@/types/artifacts";
import { InputSource } from "@/types/input-sources";
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
	group_by: string;
}

export interface WorkflowJob extends ActionTargetItem {
	id: number;
	name: string;
	description: string;
	dependencies: WorkflowJobDependency[];
	assignments: WorkflowAssignment[];
	use_input_source: boolean;
}

export interface WorkflowJobRun {
	id: number;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
	workflowJob: WorkflowJob;
	tasks: WorkflowTask[];
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
	inputSource?: InputSource;
	workflowJobRuns?: WorkflowJobRun[];
	artifacts?: Artifact[];
}
