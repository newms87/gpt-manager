import { ActionTargetItem, AnyObject } from "quasar-ui-danx";

export interface McpServer extends ActionTargetItem {
	description?: string;
	server_url: string;
	headers?: AnyObject;
	allowed_tools?: string[];
}
