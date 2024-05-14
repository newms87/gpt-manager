import { ActionTargetItem, AnyObject } from "quasar-ui-danx/types";

export interface Audit {
	id: string;
	event: string;
	auditable_title: string;
	old_values: AnyObject;
	new_values: AnyObject;
	created_at: string;
}

export interface AuditRequest extends ActionTargetItem {
	id: string;
	session_id: string;
	user_name: string;
	environment: string;
	url: string;
	request: AnyObject;
	response: AnyObject;
	logs: string;
	time: number;
	audits: Audit[];
	created_at: string;
	updated_at: string;
}
