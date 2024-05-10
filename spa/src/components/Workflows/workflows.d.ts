import { ActionTargetItem, AnyObject } from "quasar-ui-danx/types";

export interface Workflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
}

export interface WorkflowJob extends ActionTargetItem {
	id: number;
	name: string;
	config: AnyObject;
}

export interface WorkflowRun extends ActionTargetItem {
	id: number;
	status: string;
	completed_at: string;
	started_at: string;
	failed_at: string;
}
