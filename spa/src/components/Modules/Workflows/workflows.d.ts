import { Agent } from "@/components/Modules/Agents/agents";
import { InputSource } from "@/components/Modules/InputSources/input-sources";
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

}

export interface WorkflowAssignment extends ActionTargetItem {
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
	inputSource: InputSource;
	jobs: WorkflowRunJob[];
}
