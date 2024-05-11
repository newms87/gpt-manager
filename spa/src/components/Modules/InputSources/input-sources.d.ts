import { WorkflowRun } from "@/components/Modules/Workflows/workflows";
import { ActionTargetItem, AnyObject } from "quasar-ui-danx/types";

export interface InputSource extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	data: AnyObject;
	tokens: number;
	workflow_runs_count: number;
	workflowRuns?: WorkflowRun[];
}
