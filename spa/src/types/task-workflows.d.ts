import { TaskDefinition, TaskRunStatus, UsageSummary } from "@/types/task-definitions";
import { ActionTargetItem, AnyObject } from "quasar-ui-danx";

export interface TaskWorkflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	created_at: string;
	nodes?: TaskWorkflowNode[];
	taskWorkflowRuns: TaskWorkflowRun[];
}

export interface TaskWorkflowNode extends ActionTargetItem {
	id: string;
	name: string;
	settings?: TaskWorkflowNodeSettings;
	params?: AnyObject;
	taskDefinition: TaskDefinition;
	connections?: TaskWorkflowConnection[];
}

export interface TaskWorkflowConnection extends ActionTargetItem {
	id: string;
	sourceNode?: TaskWorkflowNode;
	targetNode?: TaskWorkflowNode;
	source_output_port: string;
	target_input_port: string;
	name: string;
}

export interface TaskWorkflowNodeSettings {
	x: number;
	y: number;
}

export interface TaskWorkflowRun extends ActionTargetItem {
	id: string;
	status: TaskRunStatus;
	started_at?: string;
	stopped_at?: string;
	failed_at?: string;
	completed_at?: string;
	created_at: string;
	usage?: UsageSummary;
}
