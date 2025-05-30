import { TaskProcess } from "@/types/task-definitions";
import { AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Artifact {
	id: number;
	original_artifact_id?: number;
	task_process_id?: number;
	name: string;
	position: number;
	model: string;
	text_content?: string;
	json_content?: AnyObject;
	files: UploadedFile[];
	meta?: AnyObject;
	taskProcess?: TaskProcess;
	created_at: string;
	child_artifacts_count?: number;
}
