import { JsonSchema, JsonSchemaType } from "@/types";
import { toValue } from "vue";
import { RefOrGetter } from "./types";
import { cloneSchema, getNodeName, getParentPath, getSchemaAtPath, getSchemaProperties, setSchemaProperties, updateSchemaAtPath } from "./useSchemaNavigation";
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
			const properties = getSchemaProperties(target) || {};
			const existingNames = Object.keys(properties);
			const name = generateUniqueName(baseName, existingNames);
			const position = getNextPosition(properties);

			const newProperty: JsonSchema = { type, position };
			const updatedProperties = { ...properties, [name]: newProperty };

			return setSchemaProperties(target, updatedProperties);
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
			const properties = getSchemaProperties(target);
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

			return setSchemaProperties(target, updatedProperties);
		});
	}

	/**
	 * Remove a property from the schema at the specified path.
	 */
	function removeProperty(path: string, name: string): JsonSchema {
		const schema = getSchema();
		return updateSchemaAtPath(schema, path, (target) => {
			const properties = getSchemaProperties(target);
			if (!properties || !properties[name]) {
				return target;
			}

			const updatedProperties = { ...properties };
			delete updatedProperties[name];

			return setSchemaProperties(target, updatedProperties);
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
		const properties = getSchemaProperties(targetSchema) || {};
		const existingNames = Object.keys(properties);
		const generatedName = generateUniqueName(baseName, existingNames);

		const newSchema = updateSchemaAtPath(schema, path, (target) => {
			const props = getSchemaProperties(target) || {};
			const position = getNextPosition(props);

			const newModel: JsonSchema = {
				type,
				position,
				...(type === "array"
					? { items: { type: "object", properties: {} } }
					: { properties: {} })
			};

			const updatedProperties = { ...props, [generatedName]: newModel };

			return setSchemaProperties(target, updatedProperties);
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
	 * Reorder properties at the specified path.
	 * Updates the position values to match the new order of property names.
	 */
	function reorderProperties(path: string, propertyNames: string[]): JsonSchema {
		const schema = getSchema();
		return updateSchemaAtPath(schema, path, (target) => {
			const properties = getSchemaProperties(target);
			if (!properties) {
				return target;
			}

			const updatedProperties = { ...properties };

			// Update positions based on new order
			propertyNames.forEach((name, index) => {
				if (updatedProperties[name]) {
					updatedProperties[name] = { ...updatedProperties[name], position: index };
				}
			});

			return setSchemaProperties(target, updatedProperties);
		});
	}

	/**
	 * Remove a model (object/array) at the specified path.
	 * Cannot remove the root model.
	 */
	function removeModel(path: string): JsonSchema {
		const schema = getSchema();

		// Cannot remove root
		if (path === "root") {
			return schema;
		}

		// Get the parent path and the model name to remove
		const modelName = getNodeName(path);
		const parentPath = getParentPath(path);

		return updateSchemaAtPath(schema, parentPath, (target) => {
			const properties = getSchemaProperties(target);
			if (!properties || !properties[modelName]) {
				return target;
			}

			const updatedProperties = { ...properties };
			delete updatedProperties[modelName];

			return setSchemaProperties(target, updatedProperties);
		});
	}

	return {
		addProperty,
		updateProperty,
		removeProperty,
		reorderProperties,
		addChildModel,
		updateModel,
		removeModel
	};
}
