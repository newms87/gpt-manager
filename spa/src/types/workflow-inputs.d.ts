import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ActionTargetItem, AnyObject, UploadedFile } from "quasar-ui-danx";

export interface WorkflowInputAssociation {
	id: string;
	workflow_input_id: string;
	associable_type: string;
	associable_id: string | null;
	category: string;
	created_at: string;
	updated_at: string;
}

export interface WorkflowInput extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	content: string;
	thumb?: UploadedFile;
	files: UploadedFile[];
	data: AnyObject;
	tokens: number;
	team_object_type?: string;
	team_object_id?: number;
	teamObject?: TeamObject;
	associations?: WorkflowInputAssociation[];
	created_at: string;
	updated_at: string;
}
