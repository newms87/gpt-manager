import { WorkflowRun } from "@/types";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx";

export interface WorkflowInput extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	content: string;
	thumb?: UploadedFile;
	files: UploadedFile[];
	data: AnyObject;
	tokens: number;
	workflow_runs_count: number;
	workflowRuns?: WorkflowRun[];
	has_active_workflow_run: boolean;
	created_at: string;
	updated_at: string;
}
