import { FragmentSelector, JsonSchemaType } from "@/types";
import { Node } from "@vue-flow/core";
import { computed, ComputedRef, Ref, toValue } from "vue";
import { ArtifactCategoryNodeData, FragmentModelNodeData, LayoutDirection, PropertyInfo, RefOrGetter, SelectionRollupState, SelectionMode } from "./types";

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
	/** Whether artifacts can be added to model nodes */
	artifactsEnabled?: RefOrGetter<boolean>;
	/** Model path currently adding an artifact (for loading state) */
	addingArtifactPath?: RefOrGetter<string | null>;
}

/** Union type for all node data types */
type AnyNodeData = FragmentModelNodeData | ArtifactCategoryNodeData;

/**
 * Composable that enriches graph nodes with selection/mode state for rendering.
 * Transforms raw graph nodes into VueFlow-ready nodes with all display properties.
 * Fragment model nodes are enriched with selection/edit state.
 * Artifact category nodes are passed through with position and direction updates.
 */
export function useNodeEnrichment(params: NodeEnrichmentParams): ComputedRef<Node<AnyNodeData>[]> {
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
		focusedNodePath,
		artifactsEnabled,
		addingArtifactPath
	} = params;

	return computed<Node<AnyNodeData>[]>(() => {
		const filter = toValue(typeFilter);
		const mode = toValue(selectionMode);
		const isRecursive = toValue(recursive);
		const showArtifacts = toValue(artifactsEnabled) ?? false;
		const currentAddingPath = toValue(addingArtifactPath);

		return graphNodes.value
			.map(node => {
				// Handle ACD nodes differently - just update position and direction
				if (node.type === "artifact-category") {
					return {
						...node,
						position: nodePositions.value.get(node.id) || node.position,
						data: {
							...node.data,
							direction: layoutDirection.value,
							editEnabled: effectiveEditEnabled.value
						}
					} as Node<ArtifactCategoryNodeData>;
				}

				// Enrich fragment model nodes with selection/mode state
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
						artifactsEnabled: showArtifacts,
						addingArtifact: currentAddingPath === node.data.path,
						isIncluded,
						properties,
						selectedProperties: selectedProperties ? Array.from(selectedProperties) : [],
						showProperties: showPropertiesInternal.value,
						hasAnySelection: rollupState.hasAnySelection,
						isFullySelected: rollupState.isFullySelected,
						shouldFocus: focusedNodePath.value === node.data.path
					}
				} as Node<FragmentModelNodeData>;
			})
			.filter(node => {
				// Don't filter ACD nodes
				if (node.type === "artifact-category") return true;
				// Filter fragment model nodes based on type filter
				if (!filter) return true;
				return (node.data as FragmentModelNodeData).properties.length > 0;
			});
	});
}
