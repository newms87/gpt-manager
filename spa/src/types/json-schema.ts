export type JsonSchemaType = "object" | "array" | "string" | "number" | "boolean" | "null";

export interface JsonSchema {
	id?: number;
	type: JsonSchemaType;
	format?: string;
	title?: string;
	description?: string;
	items?: JsonSchema;
	position?: number;
	properties?: {
		[key: string]: JsonSchema;
	};
}

export interface SelectionSchema {
	type: JsonSchemaType;
	children?: {
		[key: string]: SelectionSchema;
	};
}
