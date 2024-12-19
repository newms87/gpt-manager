export interface JsonSchema {
	id?: number;
	type: "object" | "array" | "string" | "number" | "boolean" | "null";
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
	type: "object" | "array" | "string" | "number" | "boolean" | "null";
	children?: {
		[key: string]: SelectionSchema;
	};
}
