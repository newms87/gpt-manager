import { FragmentSelector, JsonSchema } from "@/types";

export interface FragmentCounts {
	models: number;
	properties: number;
}

/**
 * Count models and properties in a FragmentSelector tree.
 * Models are objects/arrays, properties are primitive types.
 */
export function countSelectionItems(selector: FragmentSelector | null): FragmentCounts {
	if (!selector) return { models: 0, properties: 0 };

	let models = 0;
	let properties = 0;

	if (selector.type === "object" || selector.type === "array") {
		models++;
	} else {
		properties++;
	}

	if (selector.children) {
		for (const child of Object.values(selector.children)) {
			const childCounts = countSelectionItems(child);
			models += childCounts.models;
			properties += childCounts.properties;
		}
	}

	return { models, properties };
}

/**
 * Count models and properties in a JsonSchema tree.
 * Models are objects/arrays, properties are primitive types.
 * Handles $ref resolution using $defs.
 */
export function countSchemaItems(
	schemaNode: JsonSchema | null,
	defs?: Record<string, JsonSchema>
): FragmentCounts {
	if (!schemaNode) return { models: 0, properties: 0 };

	let models = 0;
	let properties = 0;

	// Handle $ref resolution
	const schemaWithRef = schemaNode as JsonSchema & { $ref?: string };
	if (schemaWithRef.$ref && defs) {
		const refName = schemaWithRef.$ref.replace("#/$defs/", "");
		const refSchema = defs[refName];
		if (refSchema) {
			return countSchemaItems(refSchema, defs);
		}
	}

	const schemaType = schemaNode.type;

	if (schemaType === "object") {
		models++;
		if (schemaNode.properties) {
			for (const propSchema of Object.values(schemaNode.properties)) {
				const propCounts = countSchemaItems(propSchema, defs);
				models += propCounts.models;
				properties += propCounts.properties;
			}
		}
	} else if (schemaType === "array") {
		models++;
		if (schemaNode.items) {
			const itemCounts = countSchemaItems(schemaNode.items, defs);
			models += itemCounts.models;
			properties += itemCounts.properties;
		}
	} else {
		properties++;
	}

	return { models, properties };
}
