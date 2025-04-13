import { FragmentSelector } from "@/types/prompts";

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

export interface FilterCondition {
	field: string;
	operator: string;
	value?: string;
	case_sensitive?: boolean;
	fragment_selector?: FragmentSelector;
	type: "condition";
}

export interface FilterConditionGroup {
	operator: "AND" | "OR";
	conditions: FilterCondition[];
	type: "condition_group";
}

export interface FilterConfig {
	operator: "AND" | "OR";
	conditions: (FilterCondition | FilterConditionGroup)[];
}
