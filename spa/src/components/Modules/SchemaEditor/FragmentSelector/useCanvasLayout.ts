import { determineLayoutDirection, layoutTree } from "./useFragmentSelectorLayout";
import { LayoutDirection } from "./useFragmentSelectorGraph";
import { Edge, Node, useVueFlow } from "@vue-flow/core";
import { ComputedRef, nextTick, Ref, ref } from "vue";

export interface CanvasLayoutResult {
	layoutApplied: Ref<boolean>;
	layoutDirection: Ref<LayoutDirection>;
	nodePositions: Ref<Map<string, { x: number; y: number }>>;
	triggerRelayout: () => void;
}

/**
 * Composable that handles measuring VueFlow nodes after initialization,
 * determining optimal layout direction, and applying tree layout positions.
 */
export function useCanvasLayout(
	flowId: string,
	canvasContainer: Ref<HTMLElement | null>,
	filteredNodes: ComputedRef<Node[]>,
	filteredEdges: ComputedRef<Edge[]>
): CanvasLayoutResult {
	const { onNodesInitialized, fitView, getNodes, updateNodeInternals } = useVueFlow(flowId);
	const layoutApplied = ref(false);
	const layoutDirection = ref<LayoutDirection>("LR");
	const nodePositions = ref<Map<string, { x: number; y: number }>>(new Map());

	/**
	 * Apply layout based on current node dimensions and container size.
	 * Called initially on nodes initialized, and can be called again to re-layout.
	 */
	function applyLayout(): void {
		// Gather real dimensions from VueFlow's measured nodes
		const dimensions = new Map<string, { width: number; height: number }>();
		for (const node of getNodes.value) {
			if (node.dimensions) {
				dimensions.set(node.id, { width: node.dimensions.width, height: node.dimensions.height });
			}
		}

		// Determine best layout direction based on container and graph shape
		const container = canvasContainer.value;
		const containerWidth = container?.clientWidth || 800;
		const containerHeight = container?.clientHeight || 600;
		const direction = determineLayoutDirection(containerWidth, containerHeight, filteredEdges.value, dimensions);
		layoutDirection.value = direction;

		// Run layout with real dimensions and determined direction
		const nodes = filteredNodes.value;
		layoutTree(nodes, filteredEdges.value, dimensions, direction);

		// Store computed positions so filteredNodes can reference them without resetting
		const positions = new Map<string, { x: number; y: number }>();
		for (const node of nodes) {
			positions.set(node.id, { ...node.position });
		}
		nodePositions.value = positions;

		// Update VueFlow node positions
		for (const node of getNodes.value) {
			const layoutNode = nodes.find(n => n.id === node.id);
			if (layoutNode) {
				node.position = { ...layoutNode.position };
			}
		}

		layoutApplied.value = true;

		// Force VueFlow to re-read handle bounds from DOM after positions are applied
		setTimeout(() => {
			updateNodeInternals(getNodes.value.map(n => n.id));
			nextTick(() => fitView());
		}, 500);
	}

	/**
	 * Trigger a re-layout after node dimensions change (e.g., showing/hiding properties).
	 * Waits for DOM update then re-measures and applies new layout.
	 */
	function triggerRelayout(): void {
		// Brief timeout to allow DOM to update with new node heights
		setTimeout(() => {
			updateNodeInternals(getNodes.value.map(n => n.id));
			// Wait for VueFlow to re-measure nodes
			setTimeout(() => {
				applyLayout();
			}, 100);
		}, 50);
	}

	onNodesInitialized(() => {
		applyLayout();
	});

	return { layoutApplied, layoutDirection, nodePositions, triggerRelayout };
}
