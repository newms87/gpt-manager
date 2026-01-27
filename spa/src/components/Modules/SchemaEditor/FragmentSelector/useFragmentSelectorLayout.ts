import { Edge, Node } from "@vue-flow/core";
import { ACD_NODE_GAP, ACD_NODE_HEIGHT, ACD_NODE_OFFSET, ACD_NODE_WIDTH, COLUMN_GAP, DEFAULT_NODE_HEIGHT, NODE_SEPARATION, NODE_WIDTH } from "./constants";
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
	/** Map of model node ID to total ACD space needed (width for TB, height for LR) */
	acdSpaceByNode: Map<string, number>;
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
 * Includes extra space for attached ACD nodes if present.
 */
function getSecondarySize(
	nodeId: string,
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection,
	acdSpaceByNode?: Map<string, number>
): number {
	const dim = dimensions.get(nodeId);
	const baseSize = direction === "LR" ? (dim?.height || 0) : (dim?.width || NODE_WIDTH);
	const acdSpace = acdSpaceByNode?.get(nodeId) || 0;
	return baseSize + acdSpace;
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
	return direction === "LR" ? (dim?.width || NODE_WIDTH) : (dim?.height || DEFAULT_NODE_HEIGHT);
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
 * Compute the effective primary size of a node including its ACDs.
 * In TB layout, ACDs stack vertically, so we need max of node height vs ACD stack height.
 * In LR layout, ACDs are below the node, so we add the ACD height to node height.
 */
function getEffectivePrimarySize(
	nodeId: string,
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection,
	acdHeightByNode: Map<string, number>
): number {
	const nodeSize = getPrimarySize(nodeId, dimensions, direction);
	const acdHeight = acdHeightByNode.get(nodeId) || 0;

	if (direction === "TB") {
		// In TB, ACDs are to the right but stacked vertically - take max height
		return Math.max(nodeSize, acdHeight);
	} else {
		// In LR, ACDs are below - heights don't stack in primary axis
		return nodeSize;
	}
}

/**
 * Compute the total height of ACDs attached to each model node.
 */
function computeAcdHeights(
	edges: Edge[],
	dimensions: Map<string, { width: number; height: number }>
): Map<string, number> {
	const acdHeightByNode = new Map<string, number>();

	// Find all ACD edges and group by parent
	const acdEdges = edges.filter(e => e.target.startsWith("acd-"));
	const acdsByParent = new Map<string, string[]>();
	for (const edge of acdEdges) {
		const acds = acdsByParent.get(edge.source) || [];
		acds.push(edge.target);
		acdsByParent.set(edge.source, acds);
	}

	// Calculate total height for each parent's ACDs
	for (const [parentId, acdIds] of acdsByParent) {
		let totalHeight = 0;
		for (let i = 0; i < acdIds.length; i++) {
			const dim = dimensions.get(acdIds[i]) || { width: ACD_NODE_WIDTH, height: ACD_NODE_HEIGHT };
			totalHeight += dim.height;
			if (i < acdIds.length - 1) totalHeight += ACD_NODE_GAP;
		}
		acdHeightByNode.set(parentId, totalHeight);
	}

	return acdHeightByNode;
}

/**
 * Compute tree metrics including leaf counts and level positions.
 * This separates metrics computation from layout logic.
 */
function computeTreeMetrics(
	rootId: string,
	childrenMap: Map<string, string[]>,
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection,
	edges: Edge[]
): TreeMetrics {
	const leafCountCache = new Map<string, number>();
	const maxPrimarySizeAtDepth = new Map<number, number>();

	// Compute ACD heights for each node
	const acdHeightByNode = computeAcdHeights(edges, dimensions);

	// Compute max primary size per depth level for consistent inter-level gaps
	// This now accounts for ACD heights in TB layout
	function computeDepths(nodeId: string, depth: number): void {
		const currentMax = maxPrimarySizeAtDepth.get(depth) || 0;
		const effectiveSize = getEffectivePrimarySize(nodeId, dimensions, direction, acdHeightByNode);
		maxPrimarySizeAtDepth.set(depth, Math.max(currentMax, effectiveSize));
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

	const nodeSize = getSecondarySize(nodeId, ctx.dimensions, ctx.direction, ctx.acdSpaceByNode);
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
		const prevSize = getSecondarySize(sortedChildren[i - 1], ctx.dimensions, ctx.direction, ctx.acdSpaceByNode);
		minPos = Math.max(minPos, prevPos + prevSize + NODE_SEPARATION);

		childPositions.push(minPos);
	}

	// Center parent on children block
	const firstChildCenter = childPositions[0] + getSecondarySize(sortedChildren[0], ctx.dimensions, ctx.direction, ctx.acdSpaceByNode) / 2;
	const lastIdx = sortedChildren.length - 1;
	const lastChildCenter = childPositions[lastIdx] + getSecondarySize(sortedChildren[lastIdx], ctx.dimensions, ctx.direction, ctx.acdSpaceByNode) / 2;
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
 * ACD edges are excluded since ACD nodes are positioned separately by positionAcdNodes().
 */
function buildChildrenMap(edges: Edge[]): Map<string, string[]> {
	const childrenMap = new Map<string, string[]>();
	for (const edge of edges) {
		// Skip ACD edges - they're positioned separately by positionAcdNodes()
		if (edge.target.startsWith("acd-")) continue;

		const children = childrenMap.get(edge.source) || [];
		children.push(edge.target);
		childrenMap.set(edge.source, children);
	}
	return childrenMap;
}

/**
 * Compute the maximum depth of a tree starting from a given node.
 */
function getMaxDepth(nodeId: string, childrenMap: Map<string, string[]>, depth: number = 1): number {
	const children = childrenMap.get(nodeId) || [];
	if (children.length === 0) return depth;
	return Math.max(...children.map(c => getMaxDepth(c, childrenMap, depth + 1)));
}

/**
 * Count the number of leaf nodes in a subtree.
 */
function countLeaves(nodeId: string, childrenMap: Map<string, string[]>): number {
	const children = childrenMap.get(nodeId) || [];
	if (children.length === 0) return 1;
	return children.reduce((sum, c) => sum + countLeaves(c, childrenMap), 0);
}

/**
 * Find the root node ID from a set of edges (node that is never a target).
 */
function findRootNode(edges: Edge[]): string {
	const targetNodes = new Set<string>();
	const allNodes = new Set<string>();
	for (const edge of edges) {
		targetNodes.add(edge.target);
		allNodes.add(edge.source);
		allNodes.add(edge.target);
	}
	for (const nodeId of allNodes) {
		if (!targetNodes.has(nodeId)) {
			return nodeId;
		}
	}
	return "root";
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
	const rootId = findRootNode(edges);

	const depth = getMaxDepth(rootId, childrenMap);
	const leafCount = countLeaves(rootId, childrenMap);

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
 * Compute the extra secondary-axis space needed for each model node's ACDs.
 * In TB layout: ACDs are to the right, so we need extra width.
 * In LR layout: ACDs are below, so we need extra height.
 */
function computeAcdSpace(
	edges: Edge[],
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection
): Map<string, number> {
	const acdSpaceByNode = new Map<string, number>();

	// Find all ACD edges and group by parent
	const acdEdges = edges.filter(e => e.target.startsWith("acd-"));
	const acdsByParent = new Map<string, string[]>();
	for (const edge of acdEdges) {
		const acds = acdsByParent.get(edge.source) || [];
		acds.push(edge.target);
		acdsByParent.set(edge.source, acds);
	}

	// Calculate space needed for each parent's ACDs
	for (const [parentId, acdIds] of acdsByParent) {
		if (direction === "TB") {
			// TB: ACDs are to the right, stacked vertically - need max width + offset
			let maxWidth = 0;
			for (const acdId of acdIds) {
				const dim = dimensions.get(acdId) || { width: ACD_NODE_WIDTH, height: ACD_NODE_HEIGHT };
				maxWidth = Math.max(maxWidth, dim.width);
			}
			acdSpaceByNode.set(parentId, ACD_NODE_OFFSET + maxWidth);
		} else {
			// LR: ACDs are below, stacked vertically - need total height + offset
			let totalHeight = 0;
			for (let i = 0; i < acdIds.length; i++) {
				const dim = dimensions.get(acdIds[i]) || { width: ACD_NODE_WIDTH, height: ACD_NODE_HEIGHT };
				totalHeight += dim.height;
				if (i < acdIds.length - 1) totalHeight += ACD_NODE_GAP;
			}
			acdSpaceByNode.set(parentId, ACD_NODE_OFFSET + totalHeight);
		}
	}

	return acdSpaceByNode;
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

	// Compute extra space needed for ACD nodes attached to each model
	const acdSpaceByNode = computeAcdSpace(edges, dimensions, direction);

	// Compute tree metrics (leaf counts and level positions)
	// Now accounts for ACD heights to prevent overlap with next level
	const { leafCountCache, levelPosition } = computeTreeMetrics("root", childrenMap, dimensions, direction, edges);

	// Build the layout context with all shared state
	const ctx: LayoutContext = {
		direction,
		childrenMap,
		nodeMap,
		dimensions,
		leafCountCache,
		levelPosition,
		secondaryOffsets: new Map<string, number>(),
		acdSpaceByNode
	};

	// Phase 1: Compute relative offsets via contour-based layout
	layoutSubtree("root", ctx);

	// Phase 2: Convert relative offsets to absolute positions
	assignNodePositions("root", 0, 0, ctx);

	// Phase 3: Position ACD nodes relative to their parent models
	positionAcdNodes(nodes, edges, dimensions, direction);
}

/**
 * Position ACD nodes relative to their parent model nodes.
 * ACDs are placed offset from their parent:
 * - TB layout: ACDs positioned to the RIGHT of parent
 * - LR layout: ACDs positioned BELOW parent
 */
export function positionAcdNodes(
	nodes: Node[],
	edges: Edge[],
	dimensions: Map<string, { width: number; height: number }>,
	direction: LayoutDirection
): void {
	console.log("[ACD DEBUG] positionAcdNodes called with", nodes.length, "nodes,", edges.length, "edges, direction:", direction);

	// Find all ACD edges (edges where target starts with "acd-")
	const acdEdges = edges.filter(e => e.target.startsWith("acd-"));
	console.log("[ACD DEBUG] Found", acdEdges.length, "ACD edges:", acdEdges.map(e => `${e.source} -> ${e.target}`));
	if (acdEdges.length === 0) return;

	// Build a map of node ID to node for quick lookup
	const nodeMap = new Map<string, Node>();
	for (const node of nodes) {
		nodeMap.set(node.id, node);
	}
	console.log("[ACD DEBUG] Node IDs in nodeMap:", Array.from(nodeMap.keys()));

	// Group ACDs by their parent model
	const acdsByParent = new Map<string, Node[]>();
	for (const edge of acdEdges) {
		const acdNode = nodeMap.get(edge.target);
		console.log("[ACD DEBUG] Looking for ACD node", edge.target, "found:", !!acdNode);
		if (!acdNode) continue;

		const acds = acdsByParent.get(edge.source) || [];
		acds.push(acdNode);
		acdsByParent.set(edge.source, acds);
	}

	// Position each group of ACDs relative to their parent
	for (const [parentId, acds] of acdsByParent) {
		const parent = nodeMap.get(parentId);
		console.log("[ACD DEBUG] Parent", parentId, "found:", !!parent, "position:", parent?.position);
		if (!parent) continue;

		const parentDim = dimensions.get(parentId) || { width: NODE_WIDTH, height: DEFAULT_NODE_HEIGHT };
		console.log("[ACD DEBUG] Parent dimensions:", parentDim);

		if (direction === "TB") {
			// TB layout: Position ACDs to the RIGHT of parent, stacked vertically
			const xOffset = parentDim.width + ACD_NODE_OFFSET;
			let yOffset = 0;
			for (const acd of acds) {
				const newPos = {
					x: parent.position.x + xOffset,
					y: parent.position.y + yOffset
				};
				console.log("[ACD DEBUG] Setting ACD", acd.id, "position to", newPos, "(parent.x + xOffset =", parent.position.x, "+", xOffset, ")");
				acd.position = newPos;
				const acdDim = dimensions.get(acd.id) || { width: ACD_NODE_WIDTH, height: ACD_NODE_HEIGHT };
				yOffset += acdDim.height + ACD_NODE_GAP;
			}
		} else {
			// LR layout: Position ACDs BELOW parent (same X, offset Y)
			let yOffset = parentDim.height + ACD_NODE_OFFSET;
			for (const acd of acds) {
				const newPos = {
					x: parent.position.x,
					y: parent.position.y + yOffset
				};
				console.log("[ACD DEBUG] Setting ACD", acd.id, "position to", newPos, "(parent.y + yOffset =", parent.position.y, "+", yOffset, ")");
				acd.position = newPos;
				const acdDim = dimensions.get(acd.id) || { width: ACD_NODE_WIDTH, height: ACD_NODE_HEIGHT };
				yOffset += acdDim.height + ACD_NODE_GAP;
			}
		}
	}
}
