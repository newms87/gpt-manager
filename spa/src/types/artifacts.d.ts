import { AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Artifact {
	id: number;
	name: string;
	group: string;
	model: string;
	text_content?: string;
	json_content?: AnyObject;
	files: UploadedFile[];
	created_at: string;
}
