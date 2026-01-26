import { RefOrGetter } from "./useFragmentSchemaEditor";
import { getModelProperties, getSchemaProperties, isModelType, PropertyInfo } from "./useFragmentSelectorGraph";
import { FragmentSelector, JsonSchema } from "@/types";
import { computed, reactive, toValue } from "vue";

/**
 * Selection rollup state for a node, considering all descendants.
 */
export interface SelectionRollupState {
	hasAnySelection: boolean;
	isFullySelected: boolean;
}

/**
 * Composable that manages fragment selection state for a schema graph.
 * Encapsulates all selection logic: toggling individual properties,
 * toggling all (with recursive/model-only modes), building the
 * FragmentSelector output, and syncing from external values.
 *
 * Selection is controlled by two parameters:
 * - selectionMode: "by-model" (include models only) | "by-property" (include properties)
 * - recursive: whether to recurse into child models
 */
export function useFragmentSelection(
	schema: RefOrGetter<JsonSchema>,
	selectionMode: RefOrGetter<"by-model" | "by-property">,
	recursive: RefOrGetter<boolean> = () => true
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
	 * Remove a node from its parent's selection set.
	 * Used when deselecting a node to ensure parent chain is updated.
	 *
	 * NOTE: This function only removes the child from the parent's selection set.
	 * It does NOT delete the parent from selectionMap even if the parent's set becomes empty.
	 * This is intentional: parent chain selection is "sticky" - once a parent is selected
	 * (via ensureParentChainSelected), it stays in the map until explicitly deselected.
	 *
	 * @param path - The path of the node to remove from parent
	 */
	function removeFromParentSelection(path: string): void {
		if (path === "root") return;

		const parts = path.split(".");
		const nodeName = parts.pop()!;
		const parentPath = parts.join(".") || "root";

		const parentSelection = selectionMap.get(parentPath);
		if (parentSelection) {
			parentSelection.delete(nodeName);
			// Note: We intentionally do NOT delete the parent when selection becomes empty.
			// The parent was explicitly added to the selection tree via ensureParentChainSelected,
			// and should remain there until explicitly deselected.
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
	 * Handle toggle all in by-model mode (non-recursive): toggle node inclusion without properties.
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

		if (selectAll) {
			ensureParentChainSelected(path);
		} else {
			// In by-model mode, remove the node from parent's selection
			// Parent will be preserved (with empty set) if no siblings remain
			removeFromParentSelection(path);
		}
	}

	/**
	 * Recursively toggle all models (without properties) for a node and all descendant nodes.
	 *
	 * For by-model + recursive mode, this creates a nested fragment structure by:
	 * - Adding model child names to parent selection sets
	 * - Only leaf models (models with no model children) have empty sets
	 */
	function toggleAllModelsRecursive(path: string, nodeSchema: JsonSchema, shouldSelect: boolean): void {
		if (!shouldSelect) {
			selectionMap.delete(path);
			// Recurse into model children to delete them too
			const properties = getModelProperties(nodeSchema);
			const schemaProperties = getSchemaProperties(nodeSchema);
			if (schemaProperties) {
				for (const prop of properties) {
					if (prop.isModel) {
						const childPath = `${path}.${prop.name}`;
						const childSchema = schemaProperties[prop.name];
						if (childSchema) {
							toggleAllModelsRecursive(childPath, childSchema, shouldSelect);
						}
					}
				}
			}
			return;
		}

		// Get model children
		const properties = getModelProperties(nodeSchema);
		const schemaProperties = getSchemaProperties(nodeSchema);
		const modelChildren = properties.filter(p => p.isModel);

		// If this node has model children, add their names to the selection set
		// and recurse into them
		if (modelChildren.length > 0 && schemaProperties) {
			const modelChildNames = new Set(modelChildren.map(p => p.name));
			selectionMap.set(path, modelChildNames);

			for (const prop of modelChildren) {
				const childPath = `${path}.${prop.name}`;
				const childSchema = schemaProperties[prop.name];
				if (childSchema) {
					toggleAllModelsRecursive(childPath, childSchema, shouldSelect);
				}
			}
		} else {
			// Leaf model: no model children, just add with empty set
			selectionMap.set(path, new Set());
		}
	}

	/**
	 * Handle toggle all in by-model + recursive mode: toggle node and all descendant models.
	 */
	function onToggleModelRecursive(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
			toggleAllModelsRecursive(path, getSchemaAtPath(path), selectAll);
		} else {
			removeFromParentSelection(path);
			toggleAllModelsRecursive(path, getSchemaAtPath(path), selectAll);
		}
	}

	/**
	 * Handle toggle all in recursive mode: select/deselect node and all descendants.
	 */
	function onToggleRecursive(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
		} else {
			removeFromParentSelection(path);
		}
		toggleAllRecursive(path, getSchemaAtPath(path), selectAll);
	}

	/**
	 * Handle toggle all in single-node mode: select/deselect only this node's SCALAR properties.
	 * In single-node mode, we only select scalar properties (not model properties).
	 */
	function onToggleSingleNode(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
			const nodeSchema = getSchemaAtPath(path);
			const properties = getModelProperties(nodeSchema);
			// Filter to only scalar properties (not model properties)
			const scalarProperties = properties.filter(p => !p.isModel);
			if (scalarProperties.length > 0) {
				selectionMap.set(path, new Set(scalarProperties.map(p => p.name)));
			}
		} else {
			removeFromParentSelection(path);
			selectionMap.delete(path);
		}
	}

	/**
	 * Toggle all properties for a node, respecting the current selection mode and recursive setting.
	 * Dispatches to mode-specific handlers based on:
	 * - selectionMode: "by-model" | "by-property"
	 * - recursive: true | false
	 */
	function onToggleAll(payload: { path: string; selectAll: boolean }): void {
		const { path, selectAll } = payload;
		const mode = toValue(selectionMode);
		const isRecursive = toValue(recursive);

		if (mode === "by-model") {
			if (isRecursive) {
				onToggleModelRecursive(path, selectAll);
			} else {
				onToggleModelOnly(path, selectAll);
			}
		} else {
			// by-property mode
			if (isRecursive) {
				onToggleRecursive(path, selectAll);
			} else {
				onToggleSingleNode(path, selectAll);
			}
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

	/**
	 * Check if this node is selected by its parent (i.e., parent has this node's name in its selection set).
	 * This is used to determine if a model node is "included" in the fragment output.
	 */
	function isSelectedByParent(path: string): boolean {
		if (path === "root") return false;

		const parts = path.split(".");
		const nodeName = parts.pop()!;
		const parentPath = parts.join(".") || "root";

		const parentSelection = selectionMap.get(parentPath);
		return parentSelection?.has(nodeName) ?? false;
	}

	/**
	 * Check if a path or any of its descendants has any selection.
	 * Also returns true if this node is selected by its parent (appears in fragment output).
	 * Used to determine if a model should show a selected checkbox state.
	 *
	 * THE RULE: If a path exists in selectionMap -> it appears in fragment -> checkbox MUST be checked
	 * This includes paths with empty Sets (sticky parent chain).
	 */
	function hasAnySelection(basePath: string): boolean {
		// First check if this node is selected by its parent
		// (i.e., parent has this node's name in its selection set)
		// This handles the case where a model is in the fragment but has no scalar children selected
		if (isSelectedByParent(basePath)) {
			return true;
		}

		// Check if this path EXISTS in selectionMap (regardless of Set size)
		// If a path is in the map, it will appear in the fragment output,
		// so the checkbox MUST show as checked
		if (selectionMap.has(basePath)) {
			return true;
		}

		// Then check if any descendant has selections
		for (const path of selectionMap.keys()) {
			if (path.startsWith(basePath + ".")) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a node (and optionally all its descendants) is fully selected.
	 *
	 * In recursive mode:
	 * - All scalar properties must be selected
	 * - All model properties must be selected AND fully selected recursively
	 *
	 * In single-node mode (recursive=false):
	 * - Only scalar properties must be selected
	 * - Model properties are NOT expected to be selected (we don't select them in single-node mode)
	 *
	 * @param path - The path to check
	 * @param properties - The properties of the node at this path
	 * @param recursive - Whether to check recursively (true) or single-node mode (false)
	 */
	function isFullySelected(path: string, properties: PropertyInfo[], recursive: boolean = true): boolean {
		const selections = selectionMap.get(path);

		// Get all scalar properties (non-model properties)
		const scalarProps = properties.filter(p => !p.isModel);
		// Get all model properties
		const modelProps = properties.filter(p => p.isModel);

		// In single-node mode, if there are no scalars and we have no selection, that's "fully selected"
		// for this single node (nothing to select)
		if (!recursive && scalarProps.length === 0) {
			return true;
		}

		// If no selections at this path, it's not fully selected (unless there's nothing to select)
		if (!selections) {
			return scalarProps.length === 0 && (!recursive || modelProps.length === 0);
		}

		// Check that all scalar properties are selected
		for (const prop of scalarProps) {
			if (!selections.has(prop.name)) return false;
		}

		// In single-node mode, we don't require model properties to be selected
		// because single-node mode only selects scalar properties
		if (!recursive) {
			return true;
		}

		// In recursive mode, check that all model properties are selected and fully selected recursively
		for (const prop of modelProps) {
			if (!selections.has(prop.name)) return false;

			// Get the child schema to check its properties
			const childPath = `${path}.${prop.name}`;
			const childSchema = getSchemaAtPath(childPath);
			const childProperties = getModelProperties(childSchema);

			// Recursively check if child is fully selected
			if (!isFullySelected(childPath, childProperties, recursive)) return false;
		}

		return true;
	}

	/**
	 * Compute the selection rollup state for a given path.
	 * This considers the node's own selections and all descendant selections.
	 *
	 * @param path - The path to check
	 * @param properties - The properties of the node at this path
	 * @param isRecursive - Whether to check descendants recursively (affects isFullySelected)
	 */
	function getSelectionRollupState(path: string, properties: PropertyInfo[], isRecursive: boolean = true): SelectionRollupState {
		return {
			hasAnySelection: hasAnySelection(path),
			isFullySelected: isFullySelected(path, properties, isRecursive)
		};
	}

	return {
		selectionMap,
		onToggleProperty,
		onToggleAll,
		fragmentSelector,
		syncFromExternal,
		getSelectionRollupState
	};
}
