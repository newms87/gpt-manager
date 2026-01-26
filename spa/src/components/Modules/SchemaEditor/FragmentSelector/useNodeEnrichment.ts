import { FragmentSelector, JsonSchemaType } from "@/types";
import { Node } from "@vue-flow/core";
import { computed, ComputedRef, Ref, toValue } from "vue";
import { FragmentModelNodeData, LayoutDirection, PropertyInfo, RefOrGetter, SelectionRollupState, SelectionMode } from "./types";

export interface NodeEnrichmentParams {
	graphNodes: ComputedRef<Node[]>;
	selectionMap: Map<string, Set<string>>;
	fragmentSelector: ComputedRef<FragmentSelector>;
	getSelectionRollupState: (path: string, properties: PropertyInfo[], recursive: boolean) => SelectionRollupState;
	nodePositions: Ref<Map<string, { x: number; y: number }>>;
	layoutDirection: Ref<LayoutDirection>;
	effectiveSelectionEnabled: Ref<boolean>;
	effectiveEditEnabled: Ref<boolean>;
	showPropertiesInternal: Ref<boolean>;
	selectionMode: RefOrGetter<SelectionMode>;
	recursive: RefOrGetter<boolean>;
	typeFilter: RefOrGetter<JsonSchemaType | null>;
	focusedNodePath: Ref<string | null>;
}

/**
 * Composable that enriches graph nodes with selection/mode state for rendering.
 * Transforms raw graph nodes into VueFlow-ready nodes with all display properties.
 */
export function useNodeEnrichment(params: NodeEnrichmentParams): ComputedRef<Node<FragmentModelNodeData>[]> {
	const {
		graphNodes,
		selectionMap,
		fragmentSelector,
		getSelectionRollupState,
		nodePositions,
		layoutDirection,
		effectiveSelectionEnabled,
		effectiveEditEnabled,
		showPropertiesInternal,
		selectionMode,
		recursive,
		typeFilter,
		focusedNodePath
	} = params;

	return computed<Node<FragmentModelNodeData>[]>(() => {
		const filter = toValue(typeFilter);
		const mode = toValue(selectionMode);
		const isRecursive = toValue(recursive);

		return graphNodes.value
			.map(node => {
				const selectedProperties = selectionMap.get(node.data.path);
				const properties = filter
					? node.data.properties.filter((p: PropertyInfo) => p.type === filter || p.isModel)
					: node.data.properties;

				// Check if node is included (for model-only mode)
				// For root, check if fragmentSelector has a type (meaning root is selected)
				let isIncluded = false;
				if (node.data.path === "root") {
					isIncluded = fragmentSelector.value.type !== undefined;
				} else {
					const parts = node.data.path.split(".");
					const parentPath = parts.slice(0, -1).join(".");
					const nodeName = parts[parts.length - 1];
					const parentSelection = selectionMap.get(parentPath);
					isIncluded = parentSelection?.has(nodeName) ?? false;
				}

				// Compute rollup selection state for ternary checkbox display
				const rollupState = getSelectionRollupState(node.data.path, properties, isRecursive);

				return {
					...node,
					position: nodePositions.value.get(node.id) || node.position,
					data: {
						...node.data,
						direction: layoutDirection.value,
						selectionMode: mode,
						selectionEnabled: effectiveSelectionEnabled.value,
						editEnabled: effectiveEditEnabled.value,
						isIncluded,
						properties,
						selectedProperties: selectedProperties ? Array.from(selectedProperties) : [],
						showProperties: showPropertiesInternal.value,
						hasAnySelection: rollupState.hasAnySelection,
						isFullySelected: rollupState.isFullySelected,
						shouldFocus: focusedNodePath.value === node.data.path
					}
				};
			})
			.filter(node => {
				if (!filter) return true;
				return node.data.properties.length > 0;
			});
	});
}
