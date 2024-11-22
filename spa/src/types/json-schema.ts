export interface JsonSchema {
	id?: number;
	type: string;
	format?: string;
	title?: string;
	description?: string;
	items?: JsonSchema;
	position?: number;
	properties?: {
		[key: string]: JsonSchema;
	};
}
