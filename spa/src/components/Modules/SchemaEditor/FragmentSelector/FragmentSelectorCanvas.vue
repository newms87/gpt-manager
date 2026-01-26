<template>
	<div ref="canvasContainer" class="fragment-selector-canvas w-full h-full">
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
					@add-child-model="handlers.handleAddChildModel"
					@update-model="handlers.handleUpdateModel"
					@remove-model="handlers.handleRemoveModel"
				/>
			</template>

			<!-- Top-right panel with mode toggle and Show Props button -->
			<Panel position="top-right">
				<FragmentSelectorControlPanel
					:selection-enabled="props.selectionEnabled"
					:edit-enabled="props.editEnabled"
					:is-edit-mode-active="modes.isEditModeActive.value"
					:show-properties="modes.showPropertiesInternal.value"
					:selection-mode="props.selectionMode"
					@update:is-edit-mode-active="modes.isEditModeActive.value = $event"
					@update:show-properties="modes.toggleShowProperties()"
				/>
			</Panel>
		</VueFlow>
	</div>
</template>

<script setup lang="ts">
import FragmentModelNode from "./FragmentModelNode.vue";
import FragmentSelectorControlPanel from "./FragmentSelectorControlPanel.vue";
import { LayoutDirection, SelectionMode } from "./types";
import { useCanvasLayout } from "./useCanvasLayout";
import { useFragmentSchemaEditor } from "./useFragmentSchemaEditor";
import { useFragmentSelection } from "./useFragmentSelection";
import { useFragmentSelectorEventHandlers } from "./useFragmentSelectorEventHandlers";
import { buildFragmentGraph } from "./useFragmentSelectorGraph";
import { getHandlesByDirection } from "./useFragmentSelectorLayout";
import { useFragmentSelectorModes } from "./useFragmentSelectorModes";
import { useNodeEnrichment } from "./useNodeEnrichment";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
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
}>(), {
	modelValue: null,
	selectionEnabled: false,
	editEnabled: false,
	selectionMode: "by-property",
	recursive: true,
	typeFilter: null
});

const emit = defineEmits<{
	"update:modelValue": [value: FragmentSelector | null];
	"update:schema": [schema: JsonSchema];
}>();

// Container ref for measuring available space
const canvasContainer = ref<HTMLElement | null>(null);

// Track which node should receive focus (for newly created models)
const focusedNodePath = ref<string | null>(null);

// Build the graph from the schema
const graph = computed(() => buildFragmentGraph(props.schema));

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
	focusedNodePath
});

// Compute filtered edges based on visible nodes (without direction-aware handles for layout)
const baseFilteredEdges = computed<Edge[]>(() => {
	const visibleNodeIds = new Set(enrichedNodes.value.map(n => n.id));
	return graph.value.edges
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
const filteredEdges = computed<Edge[]>(() => {
	const { sourceHandle, targetHandle } = getHandlesByDirection(layoutDirection.value);
	return baseFilteredEdges.value.map(edge => ({ ...edge, sourceHandle, targetHandle }));
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
}
</style>
