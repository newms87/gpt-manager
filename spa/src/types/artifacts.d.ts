import { AnyObject, UploadedFile } from "quasar-ui-danx";

export interface Artifact {
	id: number;
	name: string;
	group: string;
	model: string;
	content: string;
	data: AnyObject;
	files: UploadedFile[];
	created_at: string;
}
