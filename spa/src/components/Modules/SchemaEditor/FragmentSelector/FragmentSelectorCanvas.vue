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
				/>
			</template>

			<!-- Top-right panel with mode toggle and Show Props button (only when sidebar is hidden) -->
			<Panel v-if="!modes.showCodeSidebar.value" position="top-right">
				<FragmentSelectorControlPanel
					:selection-enabled="props.selectionEnabled"
					:edit-enabled="props.editEnabled"
					:is-edit-mode-active="modes.isEditModeActive.value"
					:show-properties="modes.showPropertiesInternal.value"
					:show-code="modes.showCodeSidebar.value"
					:selection-mode="props.selectionMode"
					@update:is-edit-mode-active="modes.isEditModeActive.value = $event"
					@update:show-properties="modes.toggleShowProperties()"
					@update:show-code="modes.toggleShowCode()"
				/>
			</Panel>
		</VueFlow>

		<!-- Sidebar Container (includes toggle buttons and code sidebar) -->
		<div
			v-if="modes.showCodeSidebar.value && (modes.effectiveSelectionEnabled.value || modes.effectiveEditEnabled.value)"
			class="absolute right-0 top-0 bottom-0 flex z-10"
		>
			<!-- Toggle Buttons (positioned to left of sidebar) -->
			<div class="py-3 pr-2">
				<FragmentSelectorControlPanel
					:selection-enabled="props.selectionEnabled"
					:edit-enabled="props.editEnabled"
					:is-edit-mode-active="modes.isEditModeActive.value"
					:show-properties="modes.showPropertiesInternal.value"
					:show-code="modes.showCodeSidebar.value"
					:selection-mode="props.selectionMode"
					@update:is-edit-mode-active="modes.isEditModeActive.value = $event"
					@update:show-properties="modes.toggleShowProperties()"
					@update:show-code="modes.toggleShowCode()"
				/>
			</div>

			<!-- Code Sidebar -->
			<div class="w-80 flex flex-col bg-slate-800/95 border-l border-slate-600 overflow-hidden">
				<div class="flex items-center justify-between px-4 py-3 bg-slate-700/90 border-b border-slate-600">
					<span class="text-sm font-medium text-slate-200">
						{{ modes.effectiveSelectionEnabled.value ? 'Selection' : 'Schema' }}
					</span>
					<div class="flex items-center gap-3 text-xs text-slate-400">
						<span>Models: {{ sidebarCounts.models }}</span>
						<span>Props: {{ sidebarCounts.properties }}</span>
					</div>
				</div>
				<div class="flex-1 min-h-0 overflow-auto">
					<CodeViewer
						:model-value="modes.effectiveSelectionEnabled.value ? selection.fragmentSelector.value : props.schema"
						editor-class="p-3"
						hide-footer
					/>
				</div>
			</div>
		</div>
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
import { CodeViewer } from "quasar-ui-danx";
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

// Helper function to count models and properties in a FragmentSelector tree
function countSelectionItems(selector: FragmentSelector | null): { models: number; properties: number } {
	if (!selector) return { models: 0, properties: 0 };

	let models = 0;
	let properties = 0;

	if (selector.type === "object" || selector.type === "array") {
		models++;
	} else {
		properties++;
	}

	if (selector.children) {
		for (const child of Object.values(selector.children)) {
			const childCounts = countSelectionItems(child);
			models += childCounts.models;
			properties += childCounts.properties;
		}
	}

	return { models, properties };
}

// Helper function to count models and properties in a JsonSchema
function countSchemaItems(schemaNode: JsonSchema | null, defs?: Record<string, JsonSchema>): { models: number; properties: number } {
	if (!schemaNode) return { models: 0, properties: 0 };

	let models = 0;
	let properties = 0;

	if (schemaNode.$ref && defs) {
		const refName = schemaNode.$ref.replace("#/$defs/", "");
		const refSchema = defs[refName];
		if (refSchema) {
			return countSchemaItems(refSchema, defs);
		}
	}

	const schemaType = schemaNode.type;

	if (schemaType === "object") {
		models++;
		if (schemaNode.properties) {
			for (const propSchema of Object.values(schemaNode.properties)) {
				const propCounts = countSchemaItems(propSchema, defs);
				models += propCounts.models;
				properties += propCounts.properties;
			}
		}
	} else if (schemaType === "array") {
		models++;
		if (schemaNode.items) {
			const itemCounts = countSchemaItems(schemaNode.items, defs);
			models += itemCounts.models;
			properties += itemCounts.properties;
		}
	} else {
		properties++;
	}

	return { models, properties };
}

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
