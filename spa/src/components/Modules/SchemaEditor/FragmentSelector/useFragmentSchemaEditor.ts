import { JsonSchema, JsonSchemaType } from "@/types";
import { toValue } from "vue";
import { RefOrGetter } from "./types";
import { cloneSchema, getSchemaAtPath, getSchemaProperties, updateSchemaAtPath } from "./useSchemaNavigation";
import { generateUniqueName, getNextPosition } from "./useSchemaNameGeneration";

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
