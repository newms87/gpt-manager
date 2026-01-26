import { JsonSchema } from "@/types";
import { PropertyInfo, SelectionRollupState } from "./types";
import { getModelProperties } from "./useFragmentSelectorGraph";
import { getSchemaProperties } from "./useSchemaNavigation";

interface ParentChainUtils {
	isSelectedByParent: (path: string) => boolean;
}

/**
 * Composable that provides query functions for selection state.
 * Allows checking if paths have selections, are fully selected, etc.
 */
export function useSelectionQueries(
	selectionMap: Map<string, Set<string>>,
	schemaGetter: () => JsonSchema,
	parentChain: ParentChainUtils
) {
	const { isSelectedByParent } = parentChain;

	/**
	 * Resolve the schema at a given dot-path (e.g., "root.items.subItems").
	 */
	function getSchemaAtPath(path: string): JsonSchema {
		const rootSchema = schemaGetter();
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
		hasAnySelection,
		isFullySelected,
		getSelectionRollupState
	};
}
