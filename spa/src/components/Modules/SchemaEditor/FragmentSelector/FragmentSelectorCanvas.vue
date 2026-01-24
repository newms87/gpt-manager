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
		</VueFlow>
	</div>
</template>

<script setup lang="ts">
import FragmentModelNode from "./FragmentModelNode.vue";
import { useCanvasLayout } from "./useCanvasLayout";
import { useFragmentSelection } from "./useFragmentSelection";
import { buildFragmentGraph } from "./useFragmentSelectorGraph";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { Background, BackgroundVariant } from "@vue-flow/background";
import { Edge, Node, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	schema: JsonSchema;
	modelValue: FragmentSelector | null;
	selectionMode?: "recursive" | "model-only";
	typeFilter?: JsonSchemaType | null;
}>(), {
	selectionMode: "recursive",
	typeFilter: null
});

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

			return {
				...node,
				position: nodePositions.value.get(node.id) || node.position,
				data: {
					...node.data,
					direction: layoutDirection.value,
					properties,
					selectedProperties: selectedProperties ? Array.from(selectedProperties) : []
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
const { layoutApplied, layoutDirection, nodePositions } = useCanvasLayout(
	"fragment-selector", canvasContainer, filteredNodes, filteredEdges
);

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
