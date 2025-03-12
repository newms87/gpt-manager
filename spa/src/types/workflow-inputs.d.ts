import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
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
	team_object_type?: string;
	team_object_id?: number;
	teamObject?: TeamObject;
	created_at: string;
	updated_at: string;
}
