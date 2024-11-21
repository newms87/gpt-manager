export interface JsonSchema {
	type: string;
	format?: string;
	title: string;
	description?: string;
	items?: JsonSchema;
	properties?: {
		[key: string]: JsonSchema;
	};
}
