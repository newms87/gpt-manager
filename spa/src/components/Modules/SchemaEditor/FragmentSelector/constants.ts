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
export const NODE_SEPARATION = 40;

/** Default width for nodes when dimensions are unknown */
export const NODE_WIDTH = 256;

/** Gap between columns/levels in the tree */
export const COLUMN_GAP = 80;
