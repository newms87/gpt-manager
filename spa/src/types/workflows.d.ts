import { Agent, AgentThread } from "@/types/agents";
import { Artifact } from "@/types/artifacts";
import { InputSource } from "@/types/input-sources";
import { ActionTargetItem, AnyObject } from "quasar-ui-danx/types";

export interface Workflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	jobs?: WorkflowJob[];
	runs?: WorkflowRun[];
}

export interface WorkflowJob extends ActionTargetItem {
	id: number;
	name: string;
	description: string;
	config: AnyObject;
	assignments: WorkflowAssignment[];
}

export interface WorkflowRunJob {
	id: number;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
	tasks: WorkflowTask[];
}

export interface WorkflowTask {
	id: number;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
	artifact?: Artifact;
	thread: AgentThread;
}

export interface WorkflowAssignment {
	id: number;
	agent: Agent;
	is_required: boolean;
	max_attempts: number;
	group: string;
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
	workflowJobRuns?: WorkflowRunJob[];
	artifacts?: Artifact[];
}
