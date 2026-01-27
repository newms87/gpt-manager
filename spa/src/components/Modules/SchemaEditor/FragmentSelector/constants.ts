/**
 * Canvas Layout Timing Constants
 * These control the delay between layout phases to ensure DOM updates are complete.
 */

/** Time (ms) for DOM to update with new node content */
export const DOM_UPDATE_DELAY_MS = 20;

/** Time (ms) for VueFlow to re-measure node dimensions */
export const NODE_MEASURE_DELAY_MS = 30;

/** Time (ms) for VueFlow to update handle bounds after position changes */
export const HANDLE_UPDATE_DELAY_MS = 150;

/**
 * Layout Spacing Constants
 * Control the spacing between nodes in the graph visualization.
 */

/** Vertical/horizontal gap between sibling nodes */
export const NODE_SEPARATION = 50;

/** Default width for nodes when dimensions are unknown */
export const NODE_WIDTH = 256;

/** Gap between columns/levels in the tree */
export const COLUMN_GAP = 80;

/**
 * Layout Defaults
 * Fallback values when dimensions or containers are not measured.
 */

/** Default node height when not measured */
export const DEFAULT_NODE_HEIGHT = 100;

/** Default container width fallback */
export const DEFAULT_CONTAINER_WIDTH = 800;

/** Default container height fallback */
export const DEFAULT_CONTAINER_HEIGHT = 600;

/**
 * Animation Timing Constants
 * Control the duration of various UI animations and delays.
 */

/** Duration for centering on a node */
export const CENTER_ON_NODE_DURATION_MS = 400;

/** Default animation duration for view transitions */
export const VIEW_ANIMATION_DURATION_MS = 500;

/** Short delay for UI updates after adding nodes */
export const NODE_ADD_DELAY_MS = 100;

/**
 * Edge Styling Constants
 * Control the appearance of edges connecting nodes.
 */

/** Default edge stroke color */
export const EDGE_STROKE_COLOR = "#64748b";

/** Default edge stroke width */
export const EDGE_STROKE_WIDTH = 1.5;

/** Border radius for edge paths */
export const EDGE_BORDER_RADIUS = 12;

/**
 * Artifact Category Definition Node Constants
 * Control the positioning of ACD nodes relative to their parent models.
 */

/** Offset for ACD node from parent (TB layout: right offset, LR layout: down offset) */
export const ACD_NODE_OFFSET = 50;

/** Gap between multiple ACD nodes on same parent */
export const ACD_NODE_GAP = 20;

/** Default width for ACD nodes */
export const ACD_NODE_WIDTH = 192;

/** Default height for ACD nodes when not measured */
export const ACD_NODE_HEIGHT = 120;

/** Stroke color for ACD edges */
export const ACD_EDGE_STROKE_COLOR = "#7c3aed";
