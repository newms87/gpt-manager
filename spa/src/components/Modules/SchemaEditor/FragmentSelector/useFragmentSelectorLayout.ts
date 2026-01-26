import { Edge, Node } from "@vue-flow/core";
import { COLUMN_GAP, NODE_SEPARATION, NODE_WIDTH } from "./constants";
import { LayoutDirection } from "./types";

/**
 * Get the appropriate source and target handle IDs based on layout direction.
 * LR: source-right -> target-left (horizontal flow)
 * TB: source-bottom -> target-top (vertical flow)
 */
export function getHandlesByDirection(direction: LayoutDirection): { sourceHandle: string; targetHandle: string } {
	return {
		sourceHandle: direction === "LR" ? "source-right" : "source-bottom",
		targetHandle: direction === "LR" ? "target-left" : "target-top"
	};
}

/**
 * Context object passed to layout helper functions containing all shared state
 * needed for tree layout calculations.
 */
interface LayoutContext {
	direction: LayoutDirection;
	childrenMap: Map<string, string[]>;
	nodeMap: Map<string, Node>;
	dimensions: Map<string, { width: number; height: number }>;
	leafCountCache: Map<string, number>;
	levelPosition: Map<number, number>;
	secondaryOffsets: Map<string, number>;
}

/**
 * Contour represents the left and right boundaries of a subtree at each depth level.
 * Used for contour-based spacing to prevent overlaps.
 */
interface Contour {
	left: number[];
	right: number[];
}

/**
 * Metrics computed during tree analysis for layout positioning.
 */
interface TreeMetrics {
	leafCountCache: Map<string, number>;
	levelPosition: Map<number, number>;
}

/**
 * Get the secondary-axis size of a node (height for LR, width for TB).
 */
function getSecondarySize(
	nodeId: string,
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection
): number {
	const dim = dimensions.get(nodeId);
	return direction === "LR" ? (dim?.height || 0) : (dim?.width || NODE_WIDTH);
}

/**
 * Get the primary-axis size of a node (width for LR, height for TB).
 */
function getPrimarySize(
	nodeId: string,
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection
): number {
	const dim = dimensions.get(nodeId);
	return direction === "LR" ? (dim?.width || NODE_WIDTH) : (dim?.height || 100);
}

/**
 * Compute the leaf count for a node (memoized).
 */
function computeLeafCount(
	nodeId: string,
	childrenMap: Map<string, string[]>,
	leafCountCache: Map<string, number>
): number {
	if (leafCountCache.has(nodeId)) return leafCountCache.get(nodeId)!;
	const children = childrenMap.get(nodeId) || [];
	const count = children.length === 0
		? 1
		: children.reduce((sum, child) => sum + computeLeafCount(child, childrenMap, leafCountCache), 0);
	leafCountCache.set(nodeId, count);
	return count;
}

/**
 * Compute tree metrics including leaf counts and level positions.
 * This separates metrics computation from layout logic.
 */
function computeTreeMetrics(
	rootId: string,
	childrenMap: Map<string, string[]>,
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection
): TreeMetrics {
	const leafCountCache = new Map<string, number>();
	const maxPrimarySizeAtDepth = new Map<number, number>();

	// Compute max primary size per depth level for consistent inter-level gaps
	function computeDepths(nodeId: string, depth: number): void {
		const currentMax = maxPrimarySizeAtDepth.get(depth) || 0;
		maxPrimarySizeAtDepth.set(depth, Math.max(currentMax, getPrimarySize(nodeId, dimensions, direction)));
		for (const child of childrenMap.get(nodeId) || []) {
			computeDepths(child, depth + 1);
		}
	}

	computeDepths(rootId, 0);

	// Build cumulative primary positions so the visual gap between levels is always COLUMN_GAP
	const levelPosition = new Map<number, number>();
	let cumulativePos = 0;
	for (let d = 0; d <= maxPrimarySizeAtDepth.size; d++) {
		levelPosition.set(d, cumulativePos);
		cumulativePos += (maxPrimarySizeAtDepth.get(d) || 0) + COLUMN_GAP;
	}

	return { leafCountCache, levelPosition };
}

/**
 * Recursively layout a subtree using contour-based spacing.
 * Returns the contour (min/max secondary positions at each depth level,
 * relative to the subtree root at position 0).
 *
 * Adjacent subtrees are spaced only as much as needed to prevent overlaps
 * at shared depth levels, producing a compact layout where shallow siblings
 * aren't pushed apart by deep subtrees they don't compete with.
 */
function layoutSubtree(nodeId: string, ctx: LayoutContext): Contour {
	const node = ctx.nodeMap.get(nodeId);
	if (!node) return { left: [], right: [] };

	const nodeSize = getSecondarySize(nodeId, ctx.dimensions, ctx.direction);
	const children = ctx.childrenMap.get(nodeId) || [];

	if (children.length === 0) {
		return { left: [0], right: [nodeSize] };
	}

	// Sort children by leaf count (smallest subtrees first)
	const sortedChildren = [...children].sort(
		(a, b) => computeLeafCount(a, ctx.childrenMap, ctx.leafCountCache) - computeLeafCount(b, ctx.childrenMap, ctx.leafCountCache)
	);

	// Layout each child subtree and get their contours
	const childContours = sortedChildren.map(childId => layoutSubtree(childId, ctx));

	// Place children using contour comparison for minimum non-overlapping spacing
	const childPositions: number[] = [0];

	for (let i = 1; i < sortedChildren.length; i++) {
		const currContour = childContours[i];
		let minPos = 0;

		// Compare with all previous subtrees' contours
		for (let prev = 0; prev < i; prev++) {
			const prevContour = childContours[prev];
			const prevPos = childPositions[prev];
			const sharedDepth = Math.min(prevContour.right.length, currContour.left.length);

			for (let d = 0; d < sharedDepth; d++) {
				const prevRight = prevPos + prevContour.right[d];
				const currLeft = currContour.left[d];
				minPos = Math.max(minPos, prevRight + NODE_SEPARATION - currLeft);
			}
		}

		// Ensure at least NODE_SEPARATION between adjacent children's own nodes
		const prevPos = childPositions[i - 1];
		const prevSize = getSecondarySize(sortedChildren[i - 1], ctx.dimensions, ctx.direction);
		minPos = Math.max(minPos, prevPos + prevSize + NODE_SEPARATION);

		childPositions.push(minPos);
	}

	// Center parent on children block
	const firstChildCenter = childPositions[0] + getSecondarySize(sortedChildren[0], ctx.dimensions, ctx.direction) / 2;
	const lastIdx = sortedChildren.length - 1;
	const lastChildCenter = childPositions[lastIdx] + getSecondarySize(sortedChildren[lastIdx], ctx.dimensions, ctx.direction) / 2;
	const childrenCenter = (firstChildCenter + lastChildCenter) / 2;
	const parentPos = childrenCenter - nodeSize / 2;

	// Shift children so parent is at position 0
	const shift = -parentPos;
	for (let i = 0; i < childPositions.length; i++) {
		childPositions[i] += shift;
		ctx.secondaryOffsets.set(sortedChildren[i], childPositions[i]);
	}

	// Build combined contour relative to parent at 0
	const combinedLeft: number[] = [0];
	const combinedRight: number[] = [nodeSize];

	const maxChildDepth = Math.max(...childContours.map(c => c.left.length));
	for (let d = 0; d < maxChildDepth; d++) {
		let levelLeft = Infinity;
		let levelRight = -Infinity;
		for (let i = 0; i < sortedChildren.length; i++) {
			if (d < childContours[i].left.length) {
				levelLeft = Math.min(levelLeft, childPositions[i] + childContours[i].left[d]);
				levelRight = Math.max(levelRight, childPositions[i] + childContours[i].right[d]);
			}
		}
		if (levelLeft !== Infinity) {
			combinedLeft.push(levelLeft);
			combinedRight.push(levelRight);
		}
	}

	return { left: combinedLeft, right: combinedRight };
}

/**
 * Convert relative offsets to absolute node positions.
 * Recursively traverses the tree and assigns final x/y coordinates to each node.
 */
function assignNodePositions(
	nodeId: string,
	depth: number,
	parentSecondary: number,
	ctx: LayoutContext
): void {
	const node = ctx.nodeMap.get(nodeId);
	if (!node) return;

	const secondary = parentSecondary + (ctx.secondaryOffsets.get(nodeId) || 0);
	const primaryPos = ctx.levelPosition.get(depth) || 0;

	node.position = ctx.direction === "LR"
		? { x: primaryPos, y: secondary }
		: { x: secondary, y: primaryPos };

	for (const child of ctx.childrenMap.get(nodeId) || []) {
		assignNodePositions(child, depth + 1, secondary, ctx);
	}
}

/**
 * Build a parent -> children adjacency map from a list of edges.
 */
function buildChildrenMap(edges: Edge[]): Map<string, string[]> {
	const childrenMap = new Map<string, string[]>();
	for (const edge of edges) {
		const children = childrenMap.get(edge.source) || [];
		children.push(edge.target);
		childrenMap.set(edge.source, children);
	}
	return childrenMap;
}

/**
 * Determine the best layout direction (LR or TB) based on the container's
 * aspect ratio and the graph's shape (depth vs breadth).
 */
export function determineLayoutDirection(
	containerWidth: number,
	containerHeight: number,
	edges: Edge[],
	dimensions: Map<string, { width: number; height: number }>
): LayoutDirection {
	const childrenMap = buildChildrenMap(edges);

	// Build target set to find root node
	const targetNodes = new Set<string>();
	for (const edge of edges) {
		targetNodes.add(edge.target);
	}

	// Find root node (node that's never a target)
	const allNodes = new Set<string>();
	for (const edge of edges) {
		allNodes.add(edge.source);
		allNodes.add(edge.target);
	}
	let rootId = "root";
	for (const nodeId of allNodes) {
		if (!targetNodes.has(nodeId)) {
			rootId = nodeId;
			break;
		}
	}

	// Compute max depth of tree
	function getMaxDepth(nodeId: string, depth: number): number {
		const children = childrenMap.get(nodeId) || [];
		if (children.length === 0) return depth;
		return Math.max(...children.map(c => getMaxDepth(c, depth + 1)));
	}

	const depth = getMaxDepth(rootId, 1);

	// Compute leaf count (nodes with no children)
	function countLeaves(nodeId: string): number {
		const children = childrenMap.get(nodeId) || [];
		if (children.length === 0) return 1;
		return children.reduce((sum, c) => sum + countLeaves(c), 0);
	}

	const leafCount = countLeaves(rootId);

	// Compute average and max height from dimensions
	const heights = Array.from(dimensions.values()).map(d => d.height);
	const avgHeight = heights.length > 0 ? heights.reduce((a, b) => a + b, 0) / heights.length : 100;
	const maxHeight = heights.length > 0 ? Math.max(...heights) : 100;

	// Estimate graph extent for LR layout
	const lrWidth = depth * (NODE_WIDTH + COLUMN_GAP);
	const lrHeight = leafCount * (avgHeight + NODE_SEPARATION);

	// Estimate graph extent for TB layout
	const tbWidth = leafCount * (NODE_WIDTH + NODE_SEPARATION);
	const tbHeight = depth * (maxHeight + COLUMN_GAP);

	// Pick direction whose aspect ratio best matches the container
	const containerAspect = containerWidth / containerHeight;
	const lrAspect = lrWidth / lrHeight;
	const tbAspect = tbWidth / tbHeight;

	return Math.abs(lrAspect - containerAspect) <= Math.abs(tbAspect - containerAspect) ? "LR" : "TB";
}

/**
 * Assign positions to nodes using a tree layout in the given direction.
 * LR: primary axis = X (depth), secondary axis = Y (siblings stacked vertically).
 * TB: primary axis = Y (depth), secondary axis = X (siblings stacked horizontally).
 *
 * Within each parent, children are sorted ascending by subtree leaf count
 * (smallest subtrees first) to prevent large subtrees from pushing smaller
 * siblings far apart.
 *
 * Uses real measured DOM dimensions instead of estimates.
 */
export function layoutTree(
	nodes: Node[],
	edges: Edge[],
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection
): void {
	const childrenMap = buildChildrenMap(edges);

	// Build a lookup map for nodes by id
	const nodeMap = new Map<string, Node>();
	for (const node of nodes) {
		nodeMap.set(node.id, node);
	}

	// Compute tree metrics (leaf counts and level positions)
	const { leafCountCache, levelPosition } = computeTreeMetrics("root", childrenMap, dimensions, direction);

	// Build the layout context with all shared state
	const ctx: LayoutContext = {
		direction,
		childrenMap,
		nodeMap,
		dimensions,
		leafCountCache,
		levelPosition,
		secondaryOffsets: new Map<string, number>()
	};

	// Phase 1: Compute relative offsets via contour-based layout
	layoutSubtree("root", ctx);

	// Phase 2: Convert relative offsets to absolute positions
	assignNodePositions("root", 0, 0, ctx);
}
