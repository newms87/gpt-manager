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
				/>
			</template>

			<!-- Show Properties Toggle (only in structure-only mode) -->
			<Panel v-if="props.selectionMode === 'structure-only'" position="top-right">
				<div
					class="flex items-center gap-2 px-3 py-1.5 rounded-lg border shadow-lg cursor-pointer transition-colors"
					:class="showPropertiesInternal
						? 'bg-sky-900/90 border-sky-600 text-sky-300'
						: 'bg-slate-800/90 border-slate-600 text-slate-400'"
					@click="toggleShowProperties"
				>
					<PropertiesIcon class="w-4" />
					<span class="text-xs">{{ showPropertiesInternal ? 'Hide Props' : 'Show Props' }}</span>
				</div>
			</Panel>
		</VueFlow>
	</div>
</template>

<script setup lang="ts">
import FragmentModelNode from "./FragmentModelNode.vue";
import { useCanvasLayout } from "./useCanvasLayout";
import { useFragmentSelection } from "./useFragmentSelection";
import { buildFragmentGraph } from "./useFragmentSelectorGraph";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { FaSolidListUl as PropertiesIcon } from "danx-icon";
import { Background, BackgroundVariant } from "@vue-flow/background";
import { Edge, Node, Panel, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { getItem, setItem } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	schema: JsonSchema;
	modelValue: FragmentSelector | null;
	selectionMode?: "recursive" | "single-node" | "structure-only";
	typeFilter?: JsonSchemaType | null;
}>(), {
	selectionMode: "recursive",
	typeFilter: null
});

// Internal state for showProperties with localStorage persistence
const showPropertiesInternal = ref<boolean>(getItem("fragmentSelector.showProperties") ?? false);

const emit = defineEmits<{
	"update:modelValue": [value: FragmentSelector | null];
}>();

// Selection logic extracted into composable
const { selectionMap, onToggleProperty, onToggleAll, fragmentSelector, syncFromExternal } = useFragmentSelection(
	() => props.schema,
	() => props.selectionMode
);

// Container ref for measuring available space
const canvasContainer = ref<HTMLElement | null>(null);

// Build the graph from the schema
const graph = computed(() => buildFragmentGraph(props.schema));

// Apply selection state and type filter to nodes
const filteredNodes = computed<Node[]>(() => {
	return graph.value.nodes
		.map(node => {
			const selectedProperties = selectionMap.get(node.data.path);
			const properties = props.typeFilter
				? node.data.properties.filter(p => p.type === props.typeFilter || p.isModel)
				: node.data.properties;

			// Check if node is included (for structure-only mode)
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

			return {
				...node,
				position: nodePositions.value.get(node.id) || node.position,
				data: {
					...node.data,
					direction: layoutDirection.value,
					selectionMode: props.selectionMode,
					isIncluded,
					properties,
					selectedProperties: selectedProperties ? Array.from(selectedProperties) : [],
					showProperties: showPropertiesInternal.value
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
	const sourceHandle = layoutDirection.value === "LR" ? "source-right" : "source-bottom";
	const targetHandle = layoutDirection.value === "LR" ? "target-left" : "target-top";
	return graph.value.edges
		.filter(edge => visibleNodeIds.has(edge.source) && visibleNodeIds.has(edge.target))
		.map(edge => ({ ...edge, sourceHandle, targetHandle }));
});

// Layout: measure nodes then apply tree positions
const { layoutApplied, layoutDirection, nodePositions, triggerRelayout } = useCanvasLayout(
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

// Watch for external modelValue changes and sync to internal state
watch(() => props.modelValue, (newVal) => {
	syncFromExternal(newVal);
}, { immediate: true });
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
