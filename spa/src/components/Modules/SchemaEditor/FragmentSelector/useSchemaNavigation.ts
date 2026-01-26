import { JsonSchema, JsonSchemaType } from "@/types";

/**
 * Get the properties record from a schema, handling both object and array types.
 * For arrays, returns items.properties; for objects, returns properties directly.
 */
export function getSchemaProperties(schema: JsonSchema): Record<string, JsonSchema> | undefined {
	return schema.items?.properties || schema.properties;
}

/**
 * Check whether a JSON schema type represents a model (object or array).
 */
export function isModelType(type: JsonSchemaType): boolean {
	return type === "object" || type === "array";
}

/**
 * Navigate to a schema node by dot-separated path.
 * Returns the schema at the specified path, or undefined if not found.
 * Handles the "root" prefix specially - it's skipped during navigation.
 */
export function getSchemaAtPath(schema: JsonSchema, path: string): JsonSchema | undefined {
	const parts = path.split(".");
	let current: JsonSchema | undefined = schema;

	// Skip "root" if it's the first part
	const startIndex = parts[0] === "root" ? 1 : 0;

	for (let i = startIndex; i < parts.length; i++) {
		if (!current) return undefined;

		const properties = getSchemaProperties(current);
		if (!properties) return undefined;

		current = properties[parts[i]];
	}

	return current;
}

/**
 * Deep clone a JsonSchema object.
 */
export function cloneSchema(schema: JsonSchema): JsonSchema {
	return JSON.parse(JSON.stringify(schema));
}

/**
 * Update a schema at a specific path immutably.
 * Returns a new schema with the update applied.
 * The updater function receives the schema at the path and returns the updated schema.
 */
export function updateSchemaAtPath(
	schema: JsonSchema,
	path: string,
	updater: (target: JsonSchema) => JsonSchema
): JsonSchema {
	const cloned = cloneSchema(schema);
	const parts = path.split(".");

	// Skip "root" if it's the first part
	const startIndex = parts[0] === "root" ? 1 : 0;

	// If path is just "root", update the root schema
	if (startIndex >= parts.length) {
		return updater(cloned);
	}

	// Navigate to the parent of the target
	let current: JsonSchema = cloned;
	for (let i = startIndex; i < parts.length - 1; i++) {
		const properties = getSchemaProperties(current);
		if (!properties) return cloned;
		current = properties[parts[i]];
		if (!current) return cloned;
	}

	// Get the properties container (handles both object and array schemas)
	const lastPart = parts[parts.length - 1];
	if (current.items?.properties) {
		current.items.properties[lastPart] = updater(current.items.properties[lastPart]);
	} else if (current.properties) {
		current.properties[lastPart] = updater(current.properties[lastPart]);
	}

	return cloned;
}
