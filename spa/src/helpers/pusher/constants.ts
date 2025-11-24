/**
 * Configuration constants for Pusher subscription management
 */

/**
 * Debounce time for batching ID-based subscriptions (milliseconds)
 */
export const SUBSCRIPTION_BATCH_DEBOUNCE_MS = 100;

/**
 * Interval for keepalive timer to refresh subscriptions (milliseconds)
 */
export const KEEPALIVE_INTERVAL_MS = 60000; // 60 seconds

/**
 * Maximum number of events to keep in the event log (FIFO)
 */
export const MAX_EVENT_LOG_SIZE = 1000;

/**
 * Pusher cluster configuration
 */
export const PUSHER_CLUSTER = "us2";
