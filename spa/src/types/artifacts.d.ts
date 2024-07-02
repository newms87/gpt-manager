import { AnyObject } from "quasar-ui-danx";

export interface Artifact {
	id: number;
	name: string;
	group: string;
	model: string;
	content: string;
	data: AnyObject;
	created_at: string;
}
