import { JsonSchema } from "@/types";
import { toValue } from "vue";
import { RefOrGetter } from "./types";
import { getModelProperties } from "./useFragmentSelectorGraph";
import { getSchemaAtPath, getSchemaProperties } from "./useSchemaNavigation";

interface ParentChainUtils {
	ensureParentChainSelected: (path: string) => void;
	removeFromParentSelection: (path: string) => void;
}

/**
 * Composable that handles all toggle operations for fragment selection.
 * Manages toggling individual properties and bulk toggle operations.
 */
export function useToggleHandlers(
	selectionMap: Map<string, Set<string>>,
	schemaGetter: () => JsonSchema,
	selectionMode: RefOrGetter<"by-model" | "by-property">,
	recursive: RefOrGetter<boolean>,
	parentChain: ParentChainUtils
) {
	const { ensureParentChainSelected, removeFromParentSelection } = parentChain;

	/**
	 * Get the schema at a path, with fallback to root schema.
	 */
	function getSchemaAtPathOrRoot(path: string): JsonSchema {
		return getSchemaAtPath(schemaGetter(), path) || schemaGetter();
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
	 * Recursively deselect all models for a node and all descendant nodes.
	 */
	function deselectModelsRecursive(path: string, nodeSchema: JsonSchema): void {
		selectionMap.delete(path);
		const properties = getModelProperties(nodeSchema);
		const schemaProperties = getSchemaProperties(nodeSchema);
		if (!schemaProperties) return;

		for (const prop of properties) {
			if (prop.isModel) {
				const childPath = `${path}.${prop.name}`;
				const childSchema = schemaProperties[prop.name];
				if (childSchema) {
					deselectModelsRecursive(childPath, childSchema);
				}
			}
		}
	}

	/**
	 * Recursively select all models for a node and all descendant nodes.
	 * Adds model child names to parent selection sets; leaf models have empty sets.
	 */
	function selectModelsRecursive(path: string, nodeSchema: JsonSchema): void {
		const properties = getModelProperties(nodeSchema);
		const schemaProperties = getSchemaProperties(nodeSchema);
		const modelChildren = properties.filter(p => p.isModel);

		if (modelChildren.length > 0 && schemaProperties) {
			const modelChildNames = new Set(modelChildren.map(p => p.name));
			selectionMap.set(path, modelChildNames);

			for (const prop of modelChildren) {
				const childPath = `${path}.${prop.name}`;
				const childSchema = schemaProperties[prop.name];
				if (childSchema) {
					selectModelsRecursive(childPath, childSchema);
				}
			}
		} else {
			selectionMap.set(path, new Set());
		}
	}

	/**
	 * Recursively toggle all models (without properties) for a node and all descendant nodes.
	 * Dispatches to select or deselect helper based on shouldSelect.
	 */
	function toggleAllModelsRecursive(path: string, nodeSchema: JsonSchema, shouldSelect: boolean): void {
		if (shouldSelect) {
			selectModelsRecursive(path, nodeSchema);
		} else {
			deselectModelsRecursive(path, nodeSchema);
		}
	}

	/**
	 * Handle toggle all in by-model + recursive mode: toggle node and all descendant models.
	 */
	function onToggleModelRecursive(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
			toggleAllModelsRecursive(path, getSchemaAtPathOrRoot(path), selectAll);
		} else {
			removeFromParentSelection(path);
			toggleAllModelsRecursive(path, getSchemaAtPathOrRoot(path), selectAll);
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
		toggleAllRecursive(path, getSchemaAtPathOrRoot(path), selectAll);
	}

	/**
	 * Handle toggle all in single-node mode: select/deselect only this node's SCALAR properties.
	 * In single-node mode, we only select scalar properties (not model properties).
	 */
	function onToggleSingleNode(path: string, selectAll: boolean): void {
		if (selectAll) {
			ensureParentChainSelected(path);
			const nodeSchema = getSchemaAtPathOrRoot(path);
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

	return {
		onToggleProperty,
		onToggleAll
	};
}
