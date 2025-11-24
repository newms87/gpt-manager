import { Channel, default as Pusher } from "pusher-js";
import { ref, Ref } from "vue";
import { KeepaliveState, PusherEvent } from "@/types/pusher-debug";
import type { ChannelEventListener, Subscription, SubscriptionBatch } from "./types";

/**
 * Pusher instance (singleton)
 */
export let pusher: Pusher | undefined;

/**
 * Set the Pusher instance
 */
export function setPusher(instance: Pusher) {
	pusher = instance;
}

/**
 * Get the Pusher instance
 */
export function getPusher(): Pusher | undefined {
	return pusher;
}

/**
 * Active Pusher channels
 */
export const channels: Channel[] = [];

/**
 * Event listeners for channels
 */
export const listeners: ChannelEventListener[] = [];

/**
 * Model-specific event listeners (tracked separately for cleanup)
 */
export const modelEventListeners: Map<string, ChannelEventListener> = new Map();

/**
 * Active subscriptions tracking
 */
export const activeSubscriptions: Ref<Map<string, Subscription>> = ref(new Map());

/**
 * Batch queue for ID-based subscriptions
 */
export const subscriptionBatchQueue: Ref<Map<string, SubscriptionBatch>> = ref(new Map());

/**
 * Connection state tracking (reactive)
 */
export const connectionState: Ref<string> = ref('initialized');

/**
 * Event log for debug panel (FIFO, max 1000 events)
 */
export const eventLog: Ref<PusherEvent[]> = ref([]);

/**
 * Event counts by type (all-time)
 */
export const eventCounts: Ref<Map<string, number>> = ref(new Map());

/**
 * Event counts per subscription (subscriptionKey => { eventName => count })
 */
export const subscriptionEventCounts: Ref<Map<string, Record<string, number>>> = ref(new Map());

/**
 * Keepalive state tracking
 */
export const keepaliveState: Ref<KeepaliveState> = ref({
	lastKeepaliveAt: null,
	nextKeepaliveAt: null,
	keepaliveCount: 0,
	lastKeepaliveSuccess: null,
	lastKeepaliveError: null
});

/**
 * Keepalive timer ID
 */
export let keepaliveTimerId: NodeJS.Timeout | null = null;

/**
 * Set keepalive timer ID
 */
export function setKeepaliveTimerId(id: NodeJS.Timeout | null) {
	keepaliveTimerId = id;
}

/**
 * Get keepalive timer ID
 */
export function getKeepaliveTimerId(): NodeJS.Timeout | null {
	return keepaliveTimerId;
}
