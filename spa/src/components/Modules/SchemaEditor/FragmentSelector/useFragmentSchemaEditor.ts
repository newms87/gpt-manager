import { JsonSchema, JsonSchemaType } from "@/types";
import { toValue } from "vue";

export type RefOrGetter<T> = T | (() => T) | { value: T };

/**
 * Navigate to a schema node by dot-separated path.
 * Returns the schema at the specified path, or undefined if not found.
 */
function getSchemaAtPath(schema: JsonSchema, path: string): JsonSchema | undefined {
	const parts = path.split(".");
	let current: JsonSchema | undefined = schema;

	// Skip "root" if it's the first part
	const startIndex = parts[0] === "root" ? 1 : 0;

	for (let i = startIndex; i < parts.length; i++) {
		if (!current) return undefined;

		const properties = current.items?.properties || current.properties;
		if (!properties) return undefined;

		current = properties[parts[i]];
	}

	return current;
}

/**
 * Get properties record from a schema, handling both object and array types.
 */
function getSchemaProperties(schema: JsonSchema): Record<string, JsonSchema> | undefined {
	return schema.items?.properties || schema.properties;
}

/**
 * Deep clone a JsonSchema object.
 */
function cloneSchema(schema: JsonSchema): JsonSchema {
	return JSON.parse(JSON.stringify(schema));
}

/**
 * Update a schema at a specific path immutably.
 * Returns a new schema with the update applied.
 */
function updateSchemaAtPath(
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
		const properties = current.items?.properties || current.properties;
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

/**
 * Generate a unique property name by auto-incrementing if necessary.
 * E.g., "prop", "prop_1", "prop_2", etc.
 */
function generateUniqueName(baseName: string, existingNames: string[]): string {
	if (!existingNames.includes(baseName)) {
		return baseName;
	}

	let count = 1;
	let name = `${baseName}_${count}`;
	while (existingNames.includes(name)) {
		count++;
		name = `${baseName}_${count}`;
	}

	return name;
}

/**
 * Get the next position value for a new property.
 */
function getNextPosition(properties: Record<string, JsonSchema>): number {
	const positions = Object.values(properties).map(p => p.position ?? 0);
	return positions.length > 0 ? Math.max(...positions) + 1 : 0;
}

/**
 * Composable for immutable schema editing operations.
 * All operations return new JsonSchema objects without mutating the original.
 */
export function useFragmentSchemaEditor(schemaRef: RefOrGetter<JsonSchema>) {
	function getSchema(): JsonSchema {
		return toValue(schemaRef);
	}

	/**
	 * Add a new property to the schema at the specified path.
	 * Returns a new schema with the property added.
	 */
	function addProperty(path: string, type: JsonSchemaType, baseName: string): JsonSchema {
		const schema = getSchema();
		return updateSchemaAtPath(schema, path, (target) => {
			const properties = target.items?.properties || target.properties || {};
			const existingNames = Object.keys(properties);
			const name = generateUniqueName(baseName, existingNames);
			const position = getNextPosition(properties);

			const newProperty: JsonSchema = { type, position };
			const updatedProperties = { ...properties, [name]: newProperty };

			if (target.items?.properties) {
				return {
					...target,
					items: { ...target.items, properties: updatedProperties }
				};
			}

			return { ...target, properties: updatedProperties };
		});
	}

	/**
	 * Update a property at the specified path.
	 * Supports renaming (originalName -> newName) and updating property attributes.
	 */
	function updateProperty(
		path: string,
		originalName: string,
		newName: string,
		updates: Partial<JsonSchema>
	): JsonSchema {
		const schema = getSchema();
		return updateSchemaAtPath(schema, path, (target) => {
			const properties = target.items?.properties || target.properties;
			if (!properties || !properties[originalName]) {
				return target;
			}

			const updatedProperties = { ...properties };
			const originalProperty = updatedProperties[originalName];

			// Create updated property
			const updatedProperty: JsonSchema = { ...originalProperty, ...updates };

			// Handle rename if names differ
			if (originalName !== newName) {
				delete updatedProperties[originalName];
				updatedProperties[newName] = updatedProperty;
			} else {
				updatedProperties[originalName] = updatedProperty;
			}

			if (target.items?.properties) {
				return {
					...target,
					items: { ...target.items, properties: updatedProperties }
				};
			}

			return { ...target, properties: updatedProperties };
		});
	}

	/**
	 * Remove a property from the schema at the specified path.
	 */
	function removeProperty(path: string, name: string): JsonSchema {
		const schema = getSchema();
		return updateSchemaAtPath(schema, path, (target) => {
			const properties = target.items?.properties || target.properties;
			if (!properties || !properties[name]) {
				return target;
			}

			const updatedProperties = { ...properties };
			delete updatedProperties[name];

			if (target.items?.properties) {
				return {
					...target,
					items: { ...target.items, properties: updatedProperties }
				};
			}

			return { ...target, properties: updatedProperties };
		});
	}

	/**
	 * Add a new child model (object or array) to the schema at the specified path.
	 * Returns both the updated schema and the generated name of the new model.
	 */
	function addChildModel(path: string, type: "object" | "array", baseName: string): { schema: JsonSchema; name: string } {
		const schema = getSchema();

		// First, determine what name will be generated
		const targetSchema = getSchemaAtPath(schema, path);
		const properties = targetSchema?.items?.properties || targetSchema?.properties || {};
		const existingNames = Object.keys(properties);
		const generatedName = generateUniqueName(baseName, existingNames);

		const newSchema = updateSchemaAtPath(schema, path, (target) => {
			const props = target.items?.properties || target.properties || {};
			const position = getNextPosition(props);

			const newModel: JsonSchema = {
				type,
				position,
				...(type === "array"
					? { items: { type: "object", properties: {} } }
					: { properties: {} })
			};

			const updatedProperties = { ...props, [generatedName]: newModel };

			if (target.items?.properties) {
				return {
					...target,
					items: { ...target.items, properties: updatedProperties }
				};
			}

			return { ...target, properties: updatedProperties };
		});

		return { schema: newSchema, name: generatedName };
	}

	/**
	 * Update the model (object/array) at the specified path.
	 * Useful for updating title, description, or other model-level attributes.
	 */
	function updateModel(path: string, updates: Partial<JsonSchema>): JsonSchema {
		const schema = getSchema();

		// If updating root, apply updates directly
		const parts = path.split(".");
		const isRoot = parts.length === 1 && parts[0] === "root";

		if (isRoot) {
			const cloned = cloneSchema(schema);
			return { ...cloned, ...updates };
		}

		return updateSchemaAtPath(schema, path, (target) => {
			return { ...target, ...updates };
		});
	}

	/**
	 * Remove a model (object/array) at the specified path.
	 * Cannot remove the root model.
	 */
	function removeModel(path: string): JsonSchema {
		const schema = getSchema();
		const parts = path.split(".");

		// Cannot remove root
		if (parts.length === 1 && parts[0] === "root") {
			return schema;
		}

		// Get the parent path and the model name to remove
		const modelName = parts[parts.length - 1];
		const parentPath = parts.slice(0, -1).join(".");

		return updateSchemaAtPath(schema, parentPath, (target) => {
			const properties = target.items?.properties || target.properties;
			if (!properties || !properties[modelName]) {
				return target;
			}

			const updatedProperties = { ...properties };
			delete updatedProperties[modelName];

			if (target.items?.properties) {
				return {
					...target,
					items: { ...target.items, properties: updatedProperties }
				};
			}

			return { ...target, properties: updatedProperties };
		});
	}

	return {
		addProperty,
		updateProperty,
		removeProperty,
		addChildModel,
		updateModel,
		removeModel
	};
}
