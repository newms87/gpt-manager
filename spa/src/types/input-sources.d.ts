import { WorkflowRun } from "@/types/workflows";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx/types";

export interface InputSource extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	content: string;
	files: UploadedFile[];
	data: AnyObject;
	tokens: number;
	workflow_runs_count: number;
	workflowRuns?: WorkflowRun[];
	created_at: string;
	updated_at: string;
}

export interface Artifact {
	id: number;
	name: string;
	group: string;
	model: string;
	content: string;
	data: AnyObject;
	created_at: string;
}
