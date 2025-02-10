import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
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
	team_object_type?: string;
	team_object_id?: number;
	teamObject?: TeamObject;
	availableTeamObjects?: TeamObject[];
	created_at: string;
	updated_at: string;
}
