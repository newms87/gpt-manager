/**
 * Pusher Integration Module
 *
 * Provides real-time event subscription capabilities via Pusher with:
 * - Automatic subscription batching for efficiency
 * - Keepalive mechanism to prevent subscription expiration
 * - Debug logging and event tracking
 * - Model-specific and channel-wide subscriptions
 */

// Export types
export type { Subscription, ChannelEventListener, SubscriptionPayload, SubscriptionBatch } from "./types";

// Export state for external access (debug panel, etc.)
export {
	activeSubscriptions,
	eventLog,
	eventCounts,
	subscriptionEventCounts,
	keepaliveState,
	connectionState,
	channels,
	getPusher
} from "./state";

// Export logger utilities
export { clearEventLog } from "./event-logger";

// Import internal dependencies
import { initializePusher, onEvent, offEvent, onModelEvent, offModelEvent } from "./channel-manager";
import { subscribeToModel, unsubscribeFromModel } from "./subscription-manager";
import {
	getPusher,
	channels,
	activeSubscriptions,
	eventLog,
	eventCounts,
	subscriptionEventCounts,
	keepaliveState,
	connectionState
} from "./state";
import { clearEventLog } from "./event-logger";

/**
 * usePusher composable
 *
 * Main entry point for Pusher functionality.
 * Initialize Pusher connection and provides subscription management.
 *
 * @example
 * ```typescript
 * import { usePusher } from "@/helpers/pusher";
 *
 * const pusher = usePusher();
 *
 * // Subscribe to model updates
 * await pusher.subscribeToModel("WorkflowRun", ["updated", "created"], workflowId);
 *
 * // Subscribe to all models of a type
 * await pusher.subscribeToModel("TaskRun", ["updated"], true);
 *
 * // Subscribe with filter
 * await pusher.subscribeToModel("WorkflowRun", ["updated"], {
 *   filter: { status: "running" }
 * });
 *
 * // Unsubscribe
 * await pusher.unsubscribeFromModel("WorkflowRun", ["updated"], workflowId);
 * ```
 */
export function usePusher() {
	// Initialize Pusher if not already initialized
	initializePusher();

	return {
		// Pusher instance and channels
		pusher: getPusher(),
		channels,

		// Subscription state (reactive)
		activeSubscriptions,
		eventLog,
		eventCounts,
		subscriptionEventCounts,
		keepaliveState,
		connectionState,

		// Event logger utilities
		clearEventLog,

		// Subscription management
		subscribeToModel,
		unsubscribeFromModel,

		// Event listeners
		onEvent,
		offEvent,
		onModelEvent,
		offModelEvent
	};
}
