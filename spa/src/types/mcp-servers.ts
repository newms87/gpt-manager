import { AnyObject } from "quasar-ui-danx";

export interface McpServer {
	id: string;
	name: string;
	label: string;
	description?: string;
	server_url: string;
	headers?: AnyObject;
	allowed_tools?: string[];
	require_approval: "never" | "always";
	is_active: boolean;
	created_at: string;
	updated_at: string;
}