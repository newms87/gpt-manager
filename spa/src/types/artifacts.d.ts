import { AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Artifact {
	id: number;
	name: string;
	position: number;
	model: string;
	text_content?: string;
	json_content?: AnyObject;
	files: UploadedFile[];
	created_at: string;
}
