<template>
	<div ref="canvasContainer" class="fragment-selector-canvas w-full h-full">
		<VueFlow
			id="fragment-selector"
			:nodes="filteredNodes"
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
					@toggle-property="handleToggleProperty"
					@toggle-all="handleToggleAll"
					@add-property="handleAddProperty"
					@update-property="handleUpdateProperty"
					@remove-property="handleRemoveProperty"
					@add-child-model="handleAddChildModel"
					@update-model="handleUpdateModel"
					@remove-model="handleRemoveModel"
				/>
			</template>

			<!-- Top-right panel with mode toggle and Show Props button -->
			<Panel position="top-right">
				<div class="flex items-center gap-2">
					<!-- Edit/Select Mode Toggle (only when both modes are enabled) -->
					<button
						v-if="props.selectionEnabled && props.editEnabled"
						class="px-3 py-1.5 text-xs rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
						:class="isEditModeActive
							? 'bg-blue-600/90 border-blue-500 text-white'
							: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
						@click="isEditModeActive = !isEditModeActive"
					>
						{{ isEditModeActive ? 'Edit Mode' : 'Select Mode' }}
					</button>

					<!-- Show Properties Toggle (only in by-model mode) -->
					<div
						v-if="props.selectionMode === 'by-model'"
						class="flex items-center gap-2 px-3 py-1.5 rounded-lg border shadow-lg cursor-pointer transition-colors"
						:class="showPropertiesInternal
							? 'bg-sky-900/90 border-sky-600 text-sky-300'
							: 'bg-slate-800/90 border-slate-600 text-slate-400'"
						@click="toggleShowProperties"
					>
						<PropertiesIcon class="w-4" />
						<span class="text-xs">{{ showPropertiesInternal ? 'Hide Props' : 'Show Props' }}</span>
					</div>
				</div>
			</Panel>
		</VueFlow>
	</div>
</template>

<script setup lang="ts">
import FragmentModelNode from "./FragmentModelNode.vue";
import { useCanvasLayout } from "./useCanvasLayout";
import { useFragmentSchemaEditor } from "./useFragmentSchemaEditor";
import { useFragmentSelection } from "./useFragmentSelection";
import { buildFragmentGraph, SelectionMode } from "./useFragmentSelectorGraph";
import { getHandlesByDirection } from "./useFragmentSelectorLayout";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { FaSolidListUl as PropertiesIcon } from "danx-icon";
import { Background, BackgroundVariant } from "@vue-flow/background";
import { Edge, Node, Panel, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { getItem, setItem } from "quasar-ui-danx";
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

// Internal state for showProperties with localStorage persistence
const showPropertiesInternal = ref<boolean>(getItem("fragmentSelector.showProperties") ?? false);

// Internal state for edit mode toggle (only used when both selectionEnabled and editEnabled are true)
const isEditModeActive = ref(false);

// Track which node should receive focus (for newly created models)
const focusedNodePath = ref<string | null>(null);

// Computed for determining effective modes when both are enabled
const effectiveSelectionEnabled = computed(() => {
	if (props.selectionEnabled && props.editEnabled) {
		return !isEditModeActive.value; // Selection when NOT in edit mode
	}
	return props.selectionEnabled;
});

const effectiveEditEnabled = computed(() => {
	if (props.selectionEnabled && props.editEnabled) {
		return isEditModeActive.value; // Edit when toggle is on
	}
	return props.editEnabled;
});

const emit = defineEmits<{
	"update:modelValue": [value: FragmentSelector | null];
	"update:schema": [schema: JsonSchema];
}>();

// Schema editing composable
const {
	addProperty,
	updateProperty,
	removeProperty,
	addChildModel,
	updateModel,
	removeModel
} = useFragmentSchemaEditor(() => props.schema);

// Selection logic extracted into composable
const { selectionMap, onToggleProperty, onToggleAll, fragmentSelector, syncFromExternal, getSelectionRollupState } = useFragmentSelection(
	() => props.schema,
	() => props.selectionMode,
	() => props.recursive
);

// Container ref for measuring available space
const canvasContainer = ref<HTMLElement | null>(null);

// Build the graph from the schema
const graph = computed(() => buildFragmentGraph(props.schema));

// Apply selection state, edit state, and type filter to nodes
const filteredNodes = computed<Node[]>(() => {
	return graph.value.nodes
		.map(node => {
			const selectedProperties = selectionMap.get(node.data.path);
			const properties = props.typeFilter
				? node.data.properties.filter(p => p.type === props.typeFilter || p.isModel)
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
			const rollupState = getSelectionRollupState(node.data.path, properties, props.recursive);

			return {
				...node,
				position: nodePositions.value.get(node.id) || node.position,
				data: {
					...node.data,
					direction: layoutDirection.value,
					selectionMode: props.selectionMode,
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
			if (!props.typeFilter) return true;
			return node.data.properties.length > 0;
		});
});

// Filter edges to only include those connecting visible nodes, with direction-aware handles
const filteredEdges = computed<Edge[]>(() => {
	const visibleNodeIds = new Set(filteredNodes.value.map(n => n.id));
	const { sourceHandle, targetHandle } = getHandlesByDirection(layoutDirection.value);
	return graph.value.edges
		.filter(edge => visibleNodeIds.has(edge.source) && visibleNodeIds.has(edge.target))
		.map(edge => ({ ...edge, sourceHandle, targetHandle }));
});

// Layout: measure nodes then apply tree positions
const { layoutApplied, layoutDirection, nodePositions, triggerRelayout, centerOnNode } = useCanvasLayout(
	"fragment-selector", canvasContainer, filteredNodes, filteredEdges
);

// Toggle show properties with persistence and re-layout
function toggleShowProperties(): void {
	showPropertiesInternal.value = !showPropertiesInternal.value;
	setItem("fragmentSelector.showProperties", showPropertiesInternal.value);
	// Trigger re-layout since node heights will change
	triggerRelayout();
}

// Toggle handlers that also emit the updated selector
function handleToggleProperty(payload: { path: string; propertyName: string }): void {
	onToggleProperty(payload);
	emit("update:modelValue", fragmentSelector.value);
}

function handleToggleAll(payload: { path: string; selectAll: boolean }): void {
	onToggleAll(payload);
	emit("update:modelValue", fragmentSelector.value);
}

// Edit mode handlers
function handleAddProperty(payload: { path: string; type: string; baseName: string }): void {
	if (!effectiveEditEnabled.value) return;
	const newSchema = addProperty(payload.path, payload.type as JsonSchemaType, payload.baseName);
	emit("update:schema", newSchema);
	nextTick(() => triggerRelayout());
}

function handleUpdateProperty(payload: { path: string; originalName: string; newName: string; updates: object }): void {
	if (!effectiveEditEnabled.value) return;
	const newSchema = updateProperty(payload.path, payload.originalName, payload.newName, payload.updates as Partial<JsonSchema>);
	emit("update:schema", newSchema);
}

function handleRemoveProperty(payload: { path: string; name: string }): void {
	if (!effectiveEditEnabled.value) return;
	const newSchema = removeProperty(payload.path, payload.name);
	emit("update:schema", newSchema);
	nextTick(() => triggerRelayout());
}

async function handleAddChildModel(payload: { path: string; type: "object" | "array"; baseName: string }): Promise<void> {
	if (!effectiveEditEnabled.value) return;
	const { schema: newSchema, name } = addChildModel(payload.path, payload.type, payload.baseName);
	const newNodePath = `${payload.path}.${name}`;
	emit("update:schema", newSchema);
	await nextTick();
	await triggerRelayout();
	// Smoothly pan to the new model after layout is complete
	centerOnNode(newNodePath, 400);
	// Set focus on the new node's name input after centering animation completes
	setTimeout(() => {
		focusedNodePath.value = newNodePath;
		// Clear the focus trigger after a brief delay to allow the node to react
		setTimeout(() => {
			focusedNodePath.value = null;
		}, 100);
	}, 400);
}

function handleUpdateModel(payload: { path: string; updates: object }): void {
	if (!effectiveEditEnabled.value) return;
	const newSchema = updateModel(payload.path, payload.updates as Partial<JsonSchema>);
	emit("update:schema", newSchema);
}

function handleRemoveModel(payload: { path: string }): void {
	if (!effectiveEditEnabled.value) return;
	const newSchema = removeModel(payload.path);
	emit("update:schema", newSchema);
	nextTick(() => triggerRelayout());
}

// Watch for external modelValue changes and sync to internal state
watch(() => props.modelValue, (newVal) => {
	syncFromExternal(newVal);
}, { immediate: true });

// Recalculate layout when switching between edit and select modes
watch(isEditModeActive, () => {
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
