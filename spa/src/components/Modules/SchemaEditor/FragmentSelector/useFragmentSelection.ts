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
	selectionMode: RefOrGetter<"recursive" | "single-node" | "model-only">
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
	 * Ensure all ancestor paths have the appropriate property selected.
	 * For path "root.providers.certifications", this ensures:
	 * - "root" has "providers" selected
	 * - "root.providers" has "certifications" selected
	 */
	function ensureParentChainSelected(path: string): void {
		if (path === "root") return;

		const parts = path.split(".");
		// Start from root and work down to parent of current path
		for (let i = 1; i < parts.length; i++) {
			const parentPath = parts.slice(0, i).join(".");
			const childName = parts[i];

			if (!selectionMap.has(parentPath)) {
				selectionMap.set(parentPath, new Set());
			}
			selectionMap.get(parentPath)!.add(childName);
		}
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
			// Ensure parent chain is selected when adding
			ensureParentChainSelected(path);
		}
	}

	/**
	 * Recursively select or deselect all properties of a node and all descendant nodes.
	 */
	function toggleAllRecursive(path: string, nodeSchema: JsonSchema, shouldSelect: boolean): void {
		const properties = getModelProperties(nodeSchema);

		if (shouldSelect) {
			if (properties.length === 0) return;
			selectionMap.set(path, new Set(properties.map(p => p.name)));
		} else {
			selectionMap.delete(path);
		}

		const schemaProperties = getSchemaProperties(nodeSchema);
		if (!schemaProperties) return;

		for (const prop of properties) {
			if (prop.isModel) {
				const childSchema = schemaProperties[prop.name];
				if (childSchema) {
					toggleAllRecursive(`${path}.${prop.name}`, childSchema, shouldSelect);
				}
			}
		}
	}

	/**
	 * Handle toggle all in model-only mode: toggle node inclusion without properties.
	 */
	function onToggleModelOnly(path: string, selectAll: boolean): void {
		if (path === "root") {
			if (selectAll) {
				selectionMap.set("root", new Set());
			} else {
				selectionMap.clear();
			}
			return;
		}

		const parts = path.split(".");
		const parentPath = parts.slice(0, -1).join(".");
		const nodeName = parts[parts.length - 1];

		if (selectAll) {
			ensureParentChainSelected(path);
		} else {
			const parentSelection = selectionMap.get(parentPath);
			if (parentSelection) {
				parentSelection.delete(nodeName);
				if (parentSelection.size === 0 && parentPath !== "root") {
					selectionMap.delete(parentPath);
				}
			}
		}
	}

	/**
	 * Handle toggle all in recursive mode: select/deselect node and all descendants.
	 */
	function onToggleRecursive(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
		}
		toggleAllRecursive(path, getSchemaAtPath(path), selectAll);
	}

	/**
	 * Handle toggle all in single-node mode: select/deselect only this node's properties.
	 */
	function onToggleSingleNode(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
			const nodeSchema = getSchemaAtPath(path);
			const properties = getModelProperties(nodeSchema);
			selectionMap.set(path, new Set(properties.map(p => p.name)));
		} else {
			selectionMap.delete(path);
		}
	}

	/**
	 * Toggle all properties for a node, respecting the current selection mode.
	 * Dispatches to mode-specific handlers.
	 */
	function onToggleAll(payload: { path: string; selectAll: boolean }): void {
		const { path, selectAll } = payload;
		const mode = toValue(selectionMode);

		switch (mode) {
			case "model-only":
				onToggleModelOnly(path, selectAll);
				break;
			case "recursive":
				onToggleRecursive(path, selectAll);
				break;
			case "single-node":
				onToggleSingleNode(path, selectAll);
				break;
		}
	}

	/**
	 * Build a FragmentSelector tree for the given path from the current selection state.
	 */
	function buildSelectorForPath(path: string, nodeSchema: JsonSchema): FragmentSelector | null {
		const selected = selectionMap.get(path);
		// If path is not in selectionMap at all, return null
		if (!selected) return null;

		// If selected is empty but path is in map (e.g., root with no children), return just the type
		if (selected.size === 0) {
			return { type: nodeSchema.type };
		}

		const properties = getSchemaProperties(nodeSchema);
		if (!properties) return { type: nodeSchema.type };

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
	 * Returns {} when nothing is selected, {type: "object"} when root is selected.
	 */
	const fragmentSelector = computed<FragmentSelector>(() => {
		return buildSelectorForPath("root", toValue(schema)) || {};
	});

	/**
	 * Parse a FragmentSelector into the internal selection map.
	 */
	function parseFragmentSelector(selector: FragmentSelector | null, path: string): void {
		if (!selector) return;

		// If selector has a type but no children, mark this path as selected with empty set
		// This handles model-only mode where root is selected with no children
		if (selector.type && !selector.children) {
			selectionMap.set(path, new Set());
			return;
		}

		if (!selector.children) return;

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
