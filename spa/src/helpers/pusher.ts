import { apiUrls } from "@/api";
import { authTeam, authToken } from "@/helpers";
import { PusherEvent } from "@/types/pusher-debug";
import { Channel, default as Pusher } from "pusher-js";
import { ActionTargetItem, request, storeObject } from "quasar-ui-danx";
import { ref } from "vue";
import md5 from "js-md5";

export interface ChannelEventListener {
	channel: string;
	events: string[];
	callback: (data: ActionTargetItem) => void;
}

export interface Subscription {
	resourceType: string;
	events: string[];
	modelIdOrFilter: number | true | { filter: object };
}

export interface SubscriptionPayload {
	resource_type: string;
	events: string[];
	model_id_or_filter: number | true | { filter: object };
}

let pusher: Pusher;
const channels: Channel[] = [];
const listeners: ChannelEventListener[] = [];
// Track model event listeners separately for proper cleanup
const modelEventListeners: Map<string, ChannelEventListener> = new Map();

// New subscription tracking
const activeSubscriptions = ref<Map<string, Subscription>>(new Map());
let keepaliveTimerId: NodeJS.Timeout | null = null;

// Event tracking for debug panel
const eventLog = ref<PusherEvent[]>([]);
const eventCounts = ref<Map<string, number>>(new Map());
// Track event counts per subscription (subscriptionKey => { eventName => count })
const subscriptionEventCounts = ref<Map<string, Record<string, number>>>(new Map());

/**
 * Record an event in the debug log
 */
function recordEvent(resourceType: string, eventName: string, payload: any) {
	// Extract model ID if available
	const modelId = payload?.id;

	// Add to event log (limit to 1000 events, FIFO)
	const event: PusherEvent = {
		timestamp: new Date(),
		resourceType,
		eventName,
		modelId,
		payload
	};

	eventLog.value.push(event);
	if (eventLog.value.length > 1000) {
		eventLog.value.shift(); // Remove oldest event
	}

	// Increment count for this event type
	const countKey = `${resourceType}:${eventName}`;
	const currentCount = eventCounts.value.get(countKey) || 0;
	eventCounts.value.set(countKey, currentCount + 1);

	// Track event counts per subscription
	activeSubscriptions.value.forEach((subscription, subscriptionKey) => {
		// Check if this event belongs to this subscription
		if (subscription.resourceType === resourceType && subscription.events.includes(eventName)) {
			// Check scope match
			let scopeMatches = false;

			if (subscription.modelIdOrFilter === true) {
				// Channel-wide subscription - matches all events of this type
				scopeMatches = true;
			} else if (typeof subscription.modelIdOrFilter === "number") {
				// Model-specific subscription - check if model ID matches
				scopeMatches = modelId === subscription.modelIdOrFilter;
			} else {
				// Filter-based subscription - we can't easily check if it matches without backend logic
				// For now, count all events for filter-based subscriptions
				scopeMatches = true;
			}

			if (scopeMatches) {
				// Get or create event counts for this subscription
				const subEventCounts = subscriptionEventCounts.value.get(subscriptionKey) || {};
				subEventCounts[eventName] = (subEventCounts[eventName] || 0) + 1;
				subscriptionEventCounts.value.set(subscriptionKey, subEventCounts);
			}
		}
	});
}

/**
 * Clear the event log (keeps eventCounts for all-time stats)
 */
function clearEventLog() {
	eventLog.value = [];
}

/**
 * Recursively sort object keys for consistent hashing
 */
function sortObjectKeys(obj: any): any {
	if (Array.isArray(obj)) {
		return obj.map(sortObjectKeys);
	} else if (obj !== null && typeof obj === "object") {
		return Object.keys(obj)
			.sort()
			.reduce((result, key) => {
				result[key] = sortObjectKeys(obj[key]);
				return result;
			}, {} as any);
	}
	return obj;
}

/**
 * Generate MD5 hash of filter object (MUST match backend implementation)
 */
function hashFilter(filter: object): string {
	const sorted = sortObjectKeys(filter);
	const json = JSON.stringify(sorted);
	return md5(json);
}

/**
 * Generate subscription tracking key
 */
function getSubscriptionKey(resourceType: string, modelIdOrFilter: number | true | { filter: object }): string {
	if (modelIdOrFilter === true) {
		return `${resourceType}:all`;
	}
	if (typeof modelIdOrFilter === "number") {
		return `${resourceType}:id:${modelIdOrFilter}`;
	}
	// It's a filter object
	const filterObj = (modelIdOrFilter as { filter: object }).filter;
	const hash = hashFilter(filterObj);
	return `${resourceType}:filter:${hash}`;
}

function subscribeToChannel(channelName, id, events): boolean {
	const fullName = "private-" + channelName + "." + id;

	// This channel has already been added, so return false
	if (channels.find(c => c.name === fullName)) return false;

	const channel = pusher.subscribe(fullName);

	for (const event of events) {
		channel.bind(event, function (data) {
			storeObject(data);
			fireSubscriberEvents(channelName, event, data);
		});
	}
	channels.push(channel);

	// We added a new channel so return true
	return true;
}

function fireSubscriberEvents(channel: string, event: string, data: ActionTargetItem) {
	// Record event for debug panel
	recordEvent(channel, event, data);

	for (const subscription of listeners) {
		if ([channel, "private-" + channel].includes(subscription.channel) && subscription.events.includes(event)) {
			subscription.callback(data);
		}
	}
}

/**
 * Start keepalive timer to refresh subscriptions every 60 seconds
 */
function startKeepalive() {
	if (keepaliveTimerId) return;

	keepaliveTimerId = setInterval(async () => {
		if (activeSubscriptions.value.size === 0) {
			stopKeepalive();
			return;
		}

		// Build payload from active subscriptions
		const payload = Array.from(activeSubscriptions.value.values()).map(sub => ({
			resource_type: sub.resourceType,
			events: sub.events,
			model_id_or_filter: sub.modelIdOrFilter
		}));

		try {
			await request.post(apiUrls.pusher.keepalive, { subscriptions: payload });
		} catch (error) {
			console.error("Keepalive failed:", error);
			// Let subscriptions expire naturally - don't retry
		}
	}, 60000); // 60 seconds
}

/**
 * Stop keepalive timer
 */
function stopKeepalive() {
	if (keepaliveTimerId) {
		clearInterval(keepaliveTimerId);
		keepaliveTimerId = null;
	}
}


/**
 *  usePusher is a composable that connects to Pusher and subscribes to channels.
 *  It also provides methods to listen to events on channels and models.
 */
export function usePusher() {

	if (!pusher) {
		if (!authToken.value) {
			return;
		}

		if (!authTeam.value) {
			return;
		}

		// Initialize Pusher with the auth token and configured to use the auth endpoint
		pusher = new Pusher(import.meta.env.VITE_PUSHER_API_KEY, {
			cluster: "us2",
			authEndpoint: apiUrls.auth.broadcastingAuth,
			auth: {
				headers: {
					Authorization: `Bearer ${authToken.value}`
				}
			}
		});
	}

	/**
	 * Subscribe to model updates with optional filtering
	 * @param resourceType - The model type (e.g., "WorkflowRun", "TaskRun")
	 * @param events - Array of event names (e.g., ["updated", "created"])
	 * @param modelIdOrFilter - Model ID (number), true for channel-wide, or { filter: {...} } for filter-based
	 * @returns Promise<boolean> - true if subscribed, false if already subscribed
	 */
	async function subscribeToModel(
		resourceType: string,
		events: string[],
		modelIdOrFilter: number | true | { filter: object }
	): Promise<boolean> {
		// Validate modelIdOrFilter is not null or undefined
		if (modelIdOrFilter === null || modelIdOrFilter === undefined) {
			throw new Error("modelIdOrFilter must not be null or undefined. Use true for channel-wide subscriptions.");
		}

		// Generate subscription key
		const subscriptionKey = getSubscriptionKey(resourceType, modelIdOrFilter);

		// Check if already subscribed
		if (activeSubscriptions.value.has(subscriptionKey)) {
			return false;
		}

		// Subscribe to Pusher channel
		subscribeToChannel(resourceType, authTeam.value.id, events);

		// Build payload for API call
		const payload: SubscriptionPayload = {
			resource_type: resourceType,
			events,
			model_id_or_filter: modelIdOrFilter
		};

		try {
			// Call backend API to register subscription
			await request.post(apiUrls.pusher.subscribe, payload);

			// Add to active subscriptions
			activeSubscriptions.value.set(subscriptionKey, {
				resourceType,
				events,
				modelIdOrFilter
			});

			// Start keepalive timer if not already running
			startKeepalive();

			return true;
		} catch (error) {
			console.error("Failed to subscribe to model:", error);
			throw error;
		}
	}

	/**
	 * Unsubscribe from model updates
	 * @param resourceType - The model type (e.g., "WorkflowRun", "TaskRun")
	 * @param events - Array of event names (e.g., ["updated", "created"])
	 * @param modelIdOrFilter - Model ID (number), true for channel-wide, or { filter: {...} } for filter-based
	 */
	async function unsubscribeFromModel(
		resourceType: string,
		events: string[],
		modelIdOrFilter: number | true | { filter: object }
	): Promise<void> {
		// Generate subscription key
		const subscriptionKey = getSubscriptionKey(resourceType, modelIdOrFilter);

		// Check if subscribed
		if (!activeSubscriptions.value.has(subscriptionKey)) {
			return;
		}

		// Build payload for API call
		const payload: SubscriptionPayload = {
			resource_type: resourceType,
			events,
			model_id_or_filter: modelIdOrFilter
		};

		try {
			// Call backend API to unregister subscription
			await request.post(apiUrls.pusher.unsubscribe, payload);

			// Remove from active subscriptions
			activeSubscriptions.value.delete(subscriptionKey);

			// Stop keepalive if no subscriptions remain
			if (activeSubscriptions.value.size === 0) {
				stopKeepalive();
			}
		} catch (error) {
			console.error("Failed to unsubscribe from model:", error);
			// Remove from tracking even if API call fails
			activeSubscriptions.value.delete(subscriptionKey);
			if (activeSubscriptions.value.size === 0) {
				stopKeepalive();
			}
		}
	}

	function onEvent(channel: string, event: string | string[], callback: (data: ActionTargetItem) => void) {
		listeners.push({
			channel,
			events: Array.isArray(event) ? event : [event],
			callback
		});
	}

	function onModelEvent(model: ActionTargetItem, event: string | string[], callback: (data: ActionTargetItem) => void) {
		if (!model.id) {
			return;
		}

		const channel = model.__type.replace("Resource", "");
		const events = Array.isArray(event) ? event : [event];
		
		// Create a unique key for this model event listener
		const listenerKey = `${channel}-${model.id}-${events.join(',')}-${callback.toString().substring(0, 50)}`;
		
		// Create the wrapper callback that checks for model ID
		const wrappedCallback = (data: ActionTargetItem) => {
			if (data.id === model.id) {
				callback(data);
			}
		};
		
		// Create and store the listener
		const listener: ChannelEventListener = {
			channel,
			events,
			callback: wrappedCallback
		};
		
		// Store in both maps for tracking
		modelEventListeners.set(listenerKey, listener);
		listeners.push(listener);
	}

	function offEvent(channel: string, event: string | string[], callback: (data: ActionTargetItem) => void) {
		const eventsToRemove = Array.isArray(event) ? event : [event];
		
		// Remove matching listeners
		const indexesToRemove: number[] = [];
		listeners.forEach((listener, index) => {
			if ([channel, "private-" + channel].includes(listener.channel) && 
				listener.events.some(e => eventsToRemove.includes(e)) &&
				listener.callback === callback) {
				indexesToRemove.push(index);
			}
		});
		
		// Remove listeners in reverse order to maintain correct indices
		for (let i = indexesToRemove.length - 1; i >= 0; i--) {
			listeners.splice(indexesToRemove[i], 1);
		}
	}

	function offModelEvent(model: ActionTargetItem, event: string | string[], callback: (data: ActionTargetItem) => void) {
		if (!model?.id || !model?.__type) {
			return;
		}

		const channel = model.__type.replace("Resource", "");
		const events = Array.isArray(event) ? event : [event];
		
		// Create the same unique key used in onModelEvent
		const listenerKey = `${channel}-${model.id}-${events.join(',')}-${callback.toString().substring(0, 50)}`;
		
		// Get the stored listener
		const storedListener = modelEventListeners.get(listenerKey);
		if (storedListener) {
			// Remove from modelEventListeners map
			modelEventListeners.delete(listenerKey);
			
			// Remove from listeners array
			const index = listeners.findIndex(l => l === storedListener);
			if (index !== -1) {
				listeners.splice(index, 1);
			}
		}
	}


	return {
		pusher,
		channels,
		activeSubscriptions,
		eventLog,
		eventCounts,
		subscriptionEventCounts,
		clearEventLog,
		subscribeToModel,
		unsubscribeFromModel,
		onEvent,
		offEvent,
		onModelEvent,
		offModelEvent
	};
}
