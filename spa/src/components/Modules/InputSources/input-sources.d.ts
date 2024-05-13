import { WorkflowRun } from "@/components/Modules/Workflows/workflows";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx/types";

export interface InputSource extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	files: UploadedFile[];
	data: AnyObject;
	tokens: number;
	workflow_runs_count: number;
	workflowRuns?: WorkflowRun[];
}
