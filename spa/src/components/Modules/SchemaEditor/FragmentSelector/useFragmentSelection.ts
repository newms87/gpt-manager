import { getModelProperties, getSchemaProperties, isModelType } from "./useFragmentSelectorGraph";
import { FragmentSelector, JsonSchema } from "@/types";
import { computed, reactive, toValue } from "vue";

type RefOrGetter<T> = { value: T } | (() => T);

/**
 * Composable that manages fragment selection state for a schema graph.
 * Encapsulates all selection logic: toggling individual properties,
 * toggling all (with recursive/model-only modes), building the
 * FragmentSelector output, and syncing from external values.
 */
export function useFragmentSelection(
	schema: RefOrGetter<JsonSchema>,
	selectionMode: RefOrGetter<"recursive" | "model-only">
) {
	// Internal selection state: path -> Set of selected property names
	const selectionMap = reactive(new Map<string, Set<string>>());

	/**
	 * Resolve the schema at a given dot-path (e.g., "root.items.subItems").
	 */
	function getSchemaAtPath(path: string): JsonSchema {
		const rootSchema = toValue(schema);
		if (path === "root") return rootSchema;

		const parts = path.split(".");
		let current = rootSchema;

		for (let i = 1; i < parts.length; i++) {
			const properties = getSchemaProperties(current);
			if (!properties || !properties[parts[i]]) return current;
			current = properties[parts[i]];
		}

		return current;
	}

	/**
	 * Toggle a single property's selection state at the given path.
	 */
	function onToggleProperty(payload: { path: string; propertyName: string }): void {
		const { path, propertyName } = payload;

		if (!selectionMap.has(path)) {
			selectionMap.set(path, new Set());
		}

		const selected = selectionMap.get(path)!;

		if (selected.has(propertyName)) {
			selected.delete(propertyName);
			if (selected.size === 0) {
				selectionMap.delete(path);
			}
		} else {
			selected.add(propertyName);
		}
	}

	/**
	 * Recursively select all properties of a node and all descendant nodes.
	 */
	function selectAllRecursive(path: string, nodeSchema: JsonSchema): void {
		const properties = getModelProperties(nodeSchema);
		if (properties.length === 0) return;

		selectionMap.set(path, new Set(properties.map(p => p.name)));

		const schemaProperties = getSchemaProperties(nodeSchema);
		if (!schemaProperties) return;

		for (const prop of properties) {
			if (prop.isModel) {
				const childSchema = schemaProperties[prop.name];
				if (childSchema) {
					selectAllRecursive(`${path}.${prop.name}`, childSchema);
				}
			}
		}
	}

	/**
	 * Recursively deselect all properties of a node and all descendant nodes.
	 */
	function deselectAllRecursive(path: string, nodeSchema: JsonSchema): void {
		selectionMap.delete(path);

		const properties = getModelProperties(nodeSchema);
		const schemaProperties = getSchemaProperties(nodeSchema);
		if (!schemaProperties) return;

		for (const prop of properties) {
			if (prop.isModel) {
				const childSchema = schemaProperties[prop.name];
				if (childSchema) {
					deselectAllRecursive(`${path}.${prop.name}`, childSchema);
				}
			}
		}
	}

	/**
	 * Toggle all properties for a node, respecting the current selection mode.
	 */
	function onToggleAll(payload: { path: string; selectAll: boolean }): void {
		const { path, selectAll } = payload;
		const mode = toValue(selectionMode);

		if (selectAll) {
			if (mode === "recursive") {
				selectAllRecursive(path, getSchemaAtPath(path));
			} else {
				// model-only mode: select just this node's properties
				const nodeSchema = getSchemaAtPath(path);
				const properties = getModelProperties(nodeSchema);
				selectionMap.set(path, new Set(properties.map(p => p.name)));
			}
		} else {
			if (mode === "recursive") {
				deselectAllRecursive(path, getSchemaAtPath(path));
			} else {
				selectionMap.delete(path);
			}
		}
	}

	/**
	 * Build a FragmentSelector tree for the given path from the current selection state.
	 */
	function buildSelectorForPath(path: string, nodeSchema: JsonSchema): FragmentSelector | null {
		const selected = selectionMap.get(path);
		if (!selected || selected.size === 0) return null;

		const properties = getSchemaProperties(nodeSchema);
		if (!properties) return null;

		const children: Record<string, FragmentSelector> = {};

		for (const name of selected) {
			const childSchema = properties[name];
			if (!childSchema) continue;

			if (isModelType(childSchema.type)) {
				const childPath = `${path}.${name}`;
				const childSelector = buildSelectorForPath(childPath, childSchema);
				children[name] = childSelector || { type: childSchema.type };
			} else {
				children[name] = { type: childSchema.type };
			}
		}

		return {
			type: nodeSchema.type,
			children
		};
	}

	/**
	 * Computed FragmentSelector built from the current selection state.
	 */
	const fragmentSelector = computed<FragmentSelector | null>(() => {
		return buildSelectorForPath("root", toValue(schema));
	});

	/**
	 * Parse a FragmentSelector into the internal selection map.
	 */
	function parseFragmentSelector(selector: FragmentSelector | null, path: string): void {
		if (!selector || !selector.children) return;

		const selectedNames = new Set<string>();

		for (const [childName, childSelector] of Object.entries(selector.children)) {
			selectedNames.add(childName);

			// Recurse into child models
			if (childSelector.children && isModelType(childSelector.type)) {
				parseFragmentSelector(childSelector, `${path}.${childName}`);
			}
		}

		if (selectedNames.size > 0) {
			selectionMap.set(path, selectedNames);
		}
	}

	/**
	 * Sync external FragmentSelector value into internal selection state.
	 */
	function syncFromExternal(value: FragmentSelector | null): void {
		selectionMap.clear();
		parseFragmentSelector(value, "root");
	}

	return {
		selectionMap,
		onToggleProperty,
		onToggleAll,
		fragmentSelector,
		syncFromExternal
	};
}
