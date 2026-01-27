<template>
	<div ref="canvasContainer" class="fragment-selector-canvas w-full h-full relative" :class="{ 'layout-ready': layoutApplied }">
		<VueFlow
			id="fragment-selector"
			:nodes="enrichedNodes"
			:edges="filteredEdges"
			:nodes-draggable="false"
			:nodes-connectable="false"
			:min-zoom="0.3"
			:max-zoom="2"
			:class="{ 'opacity-0': !layoutApplied }"
		>
			<Background :variant="BackgroundVariant.Dots" />
			<template #node-fragment-model="nodeProps">
				<FragmentModelNode
					:data="nodeProps.data"
					@toggle-property="handlers.handleToggleProperty"
					@toggle-all="handlers.handleToggleAll"
					@add-property="handlers.handleAddProperty"
					@update-property="handlers.handleUpdateProperty"
					@remove-property="handlers.handleRemoveProperty"
					@reorder-properties="handlers.handleReorderProperties"
					@add-child-model="handlers.handleAddChildModel"
					@update-model="handlers.handleUpdateModel"
					@remove-model="handlers.handleRemoveModel"
					@add-artifact="handlers.handleAddArtifact"
				/>
			</template>

			<template #node-artifact-category="nodeProps">
				<ArtifactCategoryNode
					:data="nodeProps.data"
					@edit="handlers.handleEditArtifact"
					@delete="handlers.handleDeleteArtifact"
				/>
			</template>

			<!-- Top-right panel with mode toggle and Show Props button (only when sidebar is hidden) -->
			<Panel v-if="!modes.showCodeSidebar.value" position="top-right">
				<FragmentSelectorControlPanel
					:modes="modes"
					:artifacts-enabled="props.artifactsEnabled"
					:artifacts-visible="props.artifactsVisible"
					:artifact-count="artifactCount"
					@toggle-artifacts="toggleArtifactsVisible"
				/>
			</Panel>
		</VueFlow>

		<!-- Code Sidebar with Control Panel -->
		<FragmentSelectorCodeSidebar
			v-if="modes.showCodeSidebar.value && (modes.effectiveSelectionEnabled.value || modes.effectiveEditEnabled.value)"
			:modes="modes"
			:data="modes.effectiveSelectionEnabled.value ? selection.fragmentSelector.value : props.schema"
			:counts="sidebarCounts"
			:artifacts-enabled="props.artifactsEnabled"
			:artifacts-visible="props.artifactsVisible"
			:artifact-count="artifactCount"
			@toggle-artifacts="toggleArtifactsVisible"
		/>
	</div>
</template>

<script setup lang="ts">
import ArtifactCategoryNode from "./ArtifactCategoryNode.vue";
import FragmentModelNode from "./FragmentModelNode.vue";
import FragmentSelectorCodeSidebar from "./FragmentSelectorCodeSidebar.vue";
import FragmentSelectorControlPanel from "./FragmentSelectorControlPanel.vue";
import { LayoutDirection, SelectionMode } from "./types";
import { useCanvasLayout } from "./useCanvasLayout";
import { countSchemaItems, countSelectionItems } from "./useFragmentSelectorCounts";
import { useFragmentSchemaEditor } from "./useFragmentSchemaEditor";
import { useFragmentSelection } from "./useFragmentSelection";
import { useFragmentSelectorEventHandlers } from "./useFragmentSelectorEventHandlers";
import { buildFragmentGraph } from "./useFragmentSelectorGraph";
import { getHandlesByDirection } from "./useFragmentSelectorLayout";
import { useFragmentSelectorModes } from "./useFragmentSelectorModes";
import { useNodeEnrichment } from "./useNodeEnrichment";
import { ArtifactCategoryDefinition, FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { Background, BackgroundVariant } from "@vue-flow/background";
import { Edge, Panel, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { computed, nextTick, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	schema: JsonSchema;
	modelValue?: FragmentSelector | null;
	selectionEnabled?: boolean;
	editEnabled?: boolean;
	selectionMode?: SelectionMode;
	recursive?: boolean;
	typeFilter?: JsonSchemaType | null;
	/** Whether artifacts can be added to model nodes */
	artifactsEnabled?: boolean;
	/** Whether artifact category nodes are visible on the canvas */
	artifactsVisible?: boolean;
	/** Artifact Category Definitions to display as nodes */
	artifactCategoryDefinitions?: ArtifactCategoryDefinition[];
	/** Model path currently adding an artifact (for loading state) */
	addingArtifactPath?: string | null;
}>(), {
	modelValue: null,
	selectionEnabled: false,
	editEnabled: false,
	selectionMode: "by-property",
	recursive: true,
	typeFilter: null,
	artifactsEnabled: false,
	artifactsVisible: false,
	artifactCategoryDefinitions: () => []
});

const emit = defineEmits<{
	"update:modelValue": [value: FragmentSelector | null];
	"update:schema": [schema: JsonSchema];
	"update:artifactsVisible": [visible: boolean];
	"add-artifact": [payload: { modelPath: string }];
	"update-artifact": [acd: ArtifactCategoryDefinition, updates: Partial<ArtifactCategoryDefinition>];
	"delete-artifact": [acd: ArtifactCategoryDefinition];
}>();

// Artifact count for control panel badge
const artifactCount = computed(() => props.artifactCategoryDefinitions.length);

// Toggle artifacts visibility handler
function toggleArtifactsVisible(): void {
	emit("update:artifactsVisible", !props.artifactsVisible);
}

// Container ref for measuring available space
const canvasContainer = ref<HTMLElement | null>(null);

// Track which node should receive focus (for newly created models)
const focusedNodePath = ref<string | null>(null);

// Build the graph from the schema (including ACD nodes when visible)
const graph = computed(() => buildFragmentGraph(props.schema, {
	artifactCategoryDefinitions: props.artifactCategoryDefinitions,
	artifactsVisible: props.artifactsVisible
}));

// Schema editing composable
const editor = useFragmentSchemaEditor(() => props.schema);

// Selection logic composable
const selection = useFragmentSelection(
	() => props.schema,
	() => props.selectionMode,
	() => props.recursive
);

// Mode state composable (needed before layout for node enrichment)
const modes = useFragmentSelectorModes(
	() => props.selectionEnabled,
	() => props.editEnabled,
	() => props.selectionMode,
	() => triggerRelayout()
);

// Node enrichment composable (moved before layout so we can compute filtered edges)
// Note: nodePositions and layoutDirection are passed as refs that will be populated by useCanvasLayout
const layoutDirection = ref<LayoutDirection>("LR");
const nodePositions = ref<Map<string, { x: number; y: number }>>(new Map());

const enrichedNodes = useNodeEnrichment({
	graphNodes: computed(() => graph.value.nodes),
	selectionMap: selection.selectionMap,
	fragmentSelector: selection.fragmentSelector,
	getSelectionRollupState: selection.getSelectionRollupState,
	nodePositions,
	layoutDirection,
	effectiveSelectionEnabled: modes.effectiveSelectionEnabled,
	effectiveEditEnabled: modes.effectiveEditEnabled,
	showPropertiesInternal: modes.showPropertiesInternal,
	selectionMode: () => props.selectionMode,
	recursive: () => props.recursive,
	typeFilter: () => props.typeFilter,
	focusedNodePath,
	artifactsEnabled: () => props.artifactsEnabled,
	addingArtifactPath: () => props.addingArtifactPath
});

// Compute filtered edges based on visible nodes (without direction-aware handles for layout)
const baseFilteredEdges = computed<Edge[]>(() => {
	const visibleNodeIds = new Set(enrichedNodes.value.map(n => n.id));
	const allEdges = graph.value.edges;
	const acdEdges = allEdges.filter(e => e.target.startsWith("acd-"));
	const acdNodeIds = Array.from(visibleNodeIds).filter(id => id.startsWith("acd-"));
	console.log("[ACD DEBUG] baseFilteredEdges: visibleNodeIds includes ACD:", acdNodeIds);
	console.log("[ACD DEBUG] baseFilteredEdges: graph has ACD edges:", acdEdges.map(e => `${e.source} -> ${e.target}`));
	acdEdges.forEach(edge => {
		console.log("[ACD DEBUG] ACD edge", edge.id, "- source in visible:", visibleNodeIds.has(edge.source), "target in visible:", visibleNodeIds.has(edge.target));
	});
	return allEdges
		.filter(edge => visibleNodeIds.has(edge.source) && visibleNodeIds.has(edge.target));
});

// Layout composable - updates layoutDirection and nodePositions refs
const { layoutApplied, layoutDirection: layoutDir, nodePositions: nodePos, triggerRelayout, centerOnNode } = useCanvasLayout(
	"fragment-selector", canvasContainer, computed(() => enrichedNodes.value), baseFilteredEdges
);

// Sync layout composable outputs back to our refs (so enrichedNodes sees updated values)
watch(layoutDir, (dir) => { layoutDirection.value = dir; }, { immediate: true });
watch(nodePos, (pos) => { nodePositions.value = pos; }, { immediate: true });

// Final filtered edges with direction-aware handles for display
// Preserves existing handles (e.g., ACD edges) and only applies defaults to edges without them
const filteredEdges = computed<Edge[]>(() => {
	const { sourceHandle, targetHandle } = getHandlesByDirection(layoutDirection.value);
	return baseFilteredEdges.value.map(edge => ({
		...edge,
		sourceHandle: edge.sourceHandle || sourceHandle,
		targetHandle: edge.targetHandle || targetHandle
	}));
});

// Event handlers composable
const handlers = useFragmentSelectorEventHandlers({
	editor,
	selection: {
		onToggleProperty: selection.onToggleProperty,
		onToggleAll: selection.onToggleAll,
		fragmentSelector: selection.fragmentSelector
	},
	emit,
	effectiveSelectionEnabled: modes.effectiveSelectionEnabled,
	effectiveEditEnabled: modes.effectiveEditEnabled,
	focusedNodePath,
	triggerRelayout,
	centerOnNode
});

// Watch for external modelValue changes and sync to internal state
watch(() => props.modelValue, (newVal) => {
	selection.syncFromExternal(newVal);
}, { immediate: true });

// Recalculate layout when switching between edit and select modes
watch(modes.isEditModeActive, () => {
	nextTick(() => triggerRelayout());
});

// Computed for sidebar counts (depends on which mode is active)
const sidebarCounts = computed(() => {
	if (modes.effectiveSelectionEnabled.value) {
		return countSelectionItems(selection.fragmentSelector.value);
	}
	return countSchemaItems(props.schema, props.schema?.$defs);
});
</script>

<style lang="scss" scoped>
.fragment-selector-canvas {
	:deep(.vue-flow) {
		transition: opacity 0.15s ease-in;
	}

	:deep(.vue-flow__node) {
		padding: 0;
		border: none;
		border-radius: 0;
		background: transparent;
	}

	// Only animate after initial layout to prevent nodes flying in from origin
	&.layout-ready :deep(.vue-flow__node) {
		transition: transform 0.3s ease-out;
	}
}
</style>
