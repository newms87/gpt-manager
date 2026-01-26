import { JsonSchema, JsonSchemaType } from "@/types";

/**
 * Represents a ref, getter function, or reactive value.
 * Used to allow composables to accept either reactive or static values.
 */
export type RefOrGetter<T> = T | (() => T) | { value: T };

/**
 * Layout direction for the graph visualization.
 * - LR: Left-to-Right (horizontal flow, primary axis = X)
 * - TB: Top-to-Bottom (vertical flow, primary axis = Y)
 */
export type LayoutDirection = "LR" | "TB";

/**
 * Selection mode for fragment selection.
 * - by-model: Select entire models (objects/arrays) without individual properties
 * - by-property: Select individual properties within models
 */
export type SelectionMode = "by-model" | "by-property";

/**
 * Information about a schema property for display purposes.
 */
export interface PropertyInfo {
	name: string;
	type: JsonSchemaType;
	format?: string;
	title?: string;
	description?: string;
	position?: number;
	isModel: boolean;
}

/**
 * Selection rollup state for a node, considering all descendants.
 */
export interface SelectionRollupState {
	hasAnySelection: boolean;
	isFullySelected: boolean;
}

/**
 * Data structure for the FragmentModelNode VueFlow node component.
 */
export interface FragmentModelNodeData {
	name: string;
	path: string;
	schema: JsonSchema;
	properties: PropertyInfo[];
	selectedProperties: string[];
	direction: LayoutDirection;
	selectionMode: SelectionMode;
	selectionEnabled?: boolean;
	editEnabled?: boolean;
	isIncluded: boolean;
	showProperties: boolean;
	/** Whether this node or any descendant has any selection */
	hasAnySelection: boolean;
	/** Whether this node and all descendants are fully selected */
	isFullySelected: boolean;
	/** Whether this node's name input should receive focus */
	shouldFocus?: boolean;
}
