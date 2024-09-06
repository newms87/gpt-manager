export interface JsonSchema {
	type: string;
	title: string;
	description?: string;
	items?: JsonSchema;
	properties?: {
		[key: string]: JsonSchema;
	};
}
