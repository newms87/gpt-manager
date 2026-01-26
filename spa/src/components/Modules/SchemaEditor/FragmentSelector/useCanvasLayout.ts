import { determineLayoutDirection, layoutTree } from "./useFragmentSelectorLayout";
import { LayoutDirection } from "./useFragmentSelectorGraph";
import { Edge, Node, useVueFlow } from "@vue-flow/core";
import { ComputedRef, nextTick, Ref, ref } from "vue";

/** Time for DOM to update with new node content */
const DOM_UPDATE_DELAY_MS = 20;

/** Time for VueFlow to re-measure node dimensions */
const NODE_MEASURE_DELAY_MS = 30;

/** Time for VueFlow to update handle bounds after position changes */
const HANDLE_UPDATE_DELAY_MS = 150;

export interface CanvasLayoutResult {
	layoutApplied: Ref<boolean>;
	layoutDirection: Ref<LayoutDirection>;
	nodePositions: Ref<Map<string, { x: number; y: number }>>;
	triggerRelayout: () => Promise<void>;
	centerOnNode: (nodeId: string, duration?: number) => void;
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
	const { onNodesInitialized, fitView, getNodes, updateNodeInternals, setCenter, getViewport } = useVueFlow(flowId);
	const layoutApplied = ref(false);
	const layoutDirection = ref<LayoutDirection>("LR");
	const nodePositions = ref<Map<string, { x: number; y: number }>>(new Map());
	const hasInitialized = ref(false); // Track if we've done the initial fit-view

	/**
	 * Apply layout based on current node dimensions and container size.
	 * Called initially on nodes initialized, and can be called again to re-layout.
	 * @param shouldFitView - Whether to fit the viewport after layout (default true for initial, false for relayouts)
	 * @returns Promise that resolves when layout is fully applied
	 */
	function applyLayout(shouldFitView: boolean = true): Promise<void> {
		return new Promise((resolve) => {
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
				if (shouldFitView) {
					nextTick(() => fitView());
				}
				resolve();
			}, HANDLE_UPDATE_DELAY_MS);
		});
	}

	/**
	 * Trigger a re-layout after node dimensions change (e.g., showing/hiding properties).
	 * Waits for DOM update then re-measures and applies new layout.
	 * Does NOT recenter the viewport to avoid disorienting users during editing.
	 * @returns Promise that resolves when layout is fully applied
	 */
	function triggerRelayout(): Promise<void> {
		return new Promise((resolve) => {
			// Brief timeout to allow DOM to update with new node heights
			setTimeout(() => {
				updateNodeInternals(getNodes.value.map(n => n.id));
				// Wait for VueFlow to re-measure nodes
				setTimeout(async () => {
					await applyLayout(false); // Don't fit view on relayout to preserve user's viewport position
					resolve();
				}, NODE_MEASURE_DELAY_MS);
			}, DOM_UPDATE_DELAY_MS);
		});
	}

	onNodesInitialized(() => {
		// Only fit view on the very first initialization
		const shouldFitView = !hasInitialized.value;
		hasInitialized.value = true;
		applyLayout(shouldFitView);
	});

	/**
	 * Smoothly pan the viewport to center on a specific node.
	 * @param nodeId - The ID of the node to center on
	 * @param duration - Animation duration in ms (default 500)
	 */
	function centerOnNode(nodeId: string, duration: number = 500): void {
		const node = getNodes.value.find(n => n.id === nodeId);
		if (!node || !node.dimensions) {
			return;
		}

		// Calculate center of the node
		const centerX = node.position.x + (node.dimensions.width / 2);
		const centerY = node.position.y + (node.dimensions.height / 2);

		// Pan to center with animation, maintaining current zoom
		setCenter(centerX, centerY, { duration, zoom: getViewport().zoom });
	}

	return { layoutApplied, layoutDirection, nodePositions, triggerRelayout, centerOnNode };
}
