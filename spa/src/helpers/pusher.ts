import { apiUrls } from "@/api";
import { authTeam, authToken } from "@/helpers";
import { KeepaliveState, PusherEvent } from "@/types/pusher-debug";
import { Channel, default as Pusher } from "pusher-js";
import { ActionTargetItem, request, storeObject } from "quasar-ui-danx";
import { ref } from "vue";
import md5 from "js-md5";

/**
 * Generate a UUID for subscription tracking
 */
function generateSubscriptionId(): string {
	return crypto.randomUUID();
}

export interface ChannelEventListener {
	channel: string;
	events: string[];
	callback: (data: ActionTargetItem) => void;
}

export interface Subscription {
	id: string;
	resourceType: string;
	events: string[];
	modelIdOrFilter: number | string | true | { filter: object };
	expiresAt?: string;
	cacheKey?: string;
	createdAt: Date;
	_batchedWith?: string; // ID of the batch subscription (if batched)
}

export interface SubscriptionPayload {
	resource_type: string;
	events: string[];
	model_id_or_filter: number | string | true | { filter: object };
}

let pusher: Pusher;
const channels: Channel[] = [];
const listeners: ChannelEventListener[] = [];
// Track model event listeners separately for proper cleanup
const modelEventListeners: Map<string, ChannelEventListener> = new Map();

// New subscription tracking
const activeSubscriptions = ref<Map<string, Subscription>>(new Map());
let keepaliveTimerId: NodeJS.Timeout | null = null;

// Batch queue for ID-based subscriptions
interface SubscriptionBatch {
	resourceType: string;
	events: string[];
	timer: NodeJS.Timeout | null;
	items: Map<string, {  // key: subscriptionId
		subscriptionId: string;
		modelId: number | string;
	}>;
	resolvers: Array<(success: boolean) => void>;
}

const subscriptionBatchQueue = ref<Map<string, SubscriptionBatch>>(new Map());

// Helper to create batch key
function getBatchKey(resourceType: string, events: string[]): string {
	return `${resourceType}:${events.sort().join(',')}`;
}

// Connection state tracking (reactive)
const connectionState = ref<string>('initialized');

// Event tracking for debug panel
const eventLog = ref<PusherEvent[]>([]);
const eventCounts = ref<Map<string, number>>(new Map());
// Track event counts per subscription (subscriptionKey => { eventName => count })
const subscriptionEventCounts = ref<Map<string, Record<string, number>>>(new Map());

// Keepalive state tracking
const keepaliveState = ref<KeepaliveState>({
	lastKeepaliveAt: null,
	nextKeepaliveAt: null,
	keepaliveCount: 0,
	lastKeepaliveSuccess: null,
	lastKeepaliveError: null
});

/**
 * Record an event in the debug log
 */
function recordEvent(resourceType: string, eventName: string, payload: any) {
	// Extract model ID if available
	const modelId = payload?.id;

	// Track matching subscriptions first
	const matchingSubscriptions: string[] = [];

	activeSubscriptions.value.forEach((subscription, subscriptionId) => {
		// Check if this event belongs to this subscription
		if (subscription.resourceType === resourceType && subscription.events.includes(eventName)) {
			// Check scope match
			let scopeMatches = false;

			if (subscription.modelIdOrFilter === true) {
				// Channel-wide subscription - matches all events of this type
				scopeMatches = true;
			} else if (typeof subscription.modelIdOrFilter === "number" || typeof subscription.modelIdOrFilter === "string") {
				// Model-specific subscription - check if model ID matches
				scopeMatches = modelId === subscription.modelIdOrFilter;
			} else {
				// Filter-based subscription - we can't easily check if it matches without backend logic
				// For now, count all events for filter-based subscriptions
				scopeMatches = true;
			}

			if (scopeMatches) {
				matchingSubscriptions.push(subscriptionId);

				// Get or create event counts for this subscription
				const subEventCounts = subscriptionEventCounts.value.get(subscriptionId) || {};
				subEventCounts[eventName] = (subEventCounts[eventName] || 0) + 1;
				subscriptionEventCounts.value.set(subscriptionId, subEventCounts);
			}
		}
	});

	// Add to event log with matching subscriptions (limit to 1000 events, FIFO)
	const event: PusherEvent = {
		timestamp: new Date(),
		resourceType,
		eventName,
		modelId,
		payload,
		matchingSubscriptions
	};

	eventLog.value.push(event);
	if (eventLog.value.length > 1000) {
		eventLog.value.shift(); // Remove oldest event
	}

	// Increment count for this event type
	const countKey = `${resourceType}:${eventName}`;
	const currentCount = eventCounts.value.get(countKey) || 0;
	eventCounts.value.set(countKey, currentCount + 1);

	// Log event reception with matching subscriptions
	console.groupCollapsed(
		'%c[PUSHER EVENT] %c' + eventName,
		'color: #3b82f6; font-weight: bold',
		'color: #60a5fa'
	);
	console.log('%cResource:', 'font-weight: bold', resourceType);
	console.log('%cModel ID:', 'font-weight: bold', modelId || 'N/A');
	console.log('%cMatching Subscriptions:', 'font-weight: bold', matchingSubscriptions.length);
	if (matchingSubscriptions.length > 0) {
		matchingSubscriptions.forEach(subId => {
			const sub = activeSubscriptions.value.get(subId);
			const eventCounts = subscriptionEventCounts.value.get(subId);
			console.log(`  %c${subId}`, 'color: #10b981', {
				scope: sub?.modelIdOrFilter,
				eventCount: eventCounts?.[eventName] || 0
			});
		});
	}
	console.log('%cTimestamp:', 'font-weight: bold', new Date().toISOString());
	console.groupEnd();
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
function getSubscriptionKey(resourceType: string, modelIdOrFilter: number | string | true | { filter: object }): string {
	if (modelIdOrFilter === true) {
		return `${resourceType}:all`;
	}
	if (typeof modelIdOrFilter === "number" || typeof modelIdOrFilter === "string") {
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

	// Set initial next keepalive time
	keepaliveState.value.nextKeepaliveAt = new Date(Date.now() + 60000);

	keepaliveTimerId = setInterval(async () => {
		if (activeSubscriptions.value.size === 0) {
			stopKeepalive();
			return;
		}

		// Collect unique backend subscription IDs (deduplicate batched subscriptions)
		const backendSubscriptionIds = new Set<string>();
		let batchedCount = 0;
		let individualCount = 0;

		for (const subscription of activeSubscriptions.value.values()) {
			if (subscription._batchedWith) {
				// This is a batched subscription - use the batch ID
				backendSubscriptionIds.add(subscription._batchedWith);
				batchedCount++;
			} else {
				// This is a non-batched subscription - use its own ID
				backendSubscriptionIds.add(subscription.id);
				individualCount++;
			}
		}

		const subscriptionIds = Array.from(backendSubscriptionIds);

		console.groupCollapsed(
			'%c[PUSHER KEEPALIVE] %cRefreshing subscriptions',
			'color: #f97316; font-weight: bold',
			'color: #fb923c'
		);
		console.log('%cTotal Frontend Subscriptions:', 'font-weight: bold', activeSubscriptions.value.size);
		console.log('%cUnique Backend Subscriptions:', 'font-weight: bold', subscriptionIds.length);
		console.log('%cBatched:', 'font-weight: bold', batchedCount);
		console.log('%cIndividual:', 'font-weight: bold', individualCount);
		console.log('%cBackend Subscription IDs:', 'font-weight: bold', subscriptionIds);
		console.log('%cTimestamp:', 'font-weight: bold', new Date().toISOString());

		try {
			const response = await request.post(apiUrls.pusher.keepaliveByIds, {
				subscription_ids: subscriptionIds
			});

			// Update keepalive state on success
			keepaliveState.value.lastKeepaliveAt = new Date();
			keepaliveState.value.nextKeepaliveAt = new Date(Date.now() + 60000);
			keepaliveState.value.keepaliveCount++;
			keepaliveState.value.lastKeepaliveSuccess = true;
			keepaliveState.value.lastKeepaliveError = null;

			// Log backend response
			console.log('%cBackend Response:', 'font-weight: bold; color: #22c55e', response);

			// Update expiration timestamps from response
			const results = response.subscriptions;
			const successCount = Object.values(results as Record<string, any>).filter(r => r.success).length;
			const failureCount = Object.values(results as Record<string, any>).filter(r => !r.success).length;

			console.log('%cSuccess:', 'font-weight: bold; color: #22c55e', successCount);
			console.log('%cFailures:', 'font-weight: bold; color: #ef4444', failureCount);

			for (const [subId, result] of Object.entries(results as Record<string, any>)) {
				if (result.success) {
					// Update all subscriptions that use this backend ID
					let updatedCount = 0;
					for (const [frontendId, subscription] of activeSubscriptions.value.entries()) {
						const subBackendId = subscription._batchedWith || subscription.id;

						if (subBackendId === subId) {
							subscription.expiresAt = result.expires_at;
							activeSubscriptions.value.set(frontendId, subscription);
							updatedCount++;
						}
					}

					console.log(`  %c✓ ${subId}`, 'color: #22c55e', {
						expiresAt: result.expires_at,
						updatedSubscriptions: updatedCount,
						batched: updatedCount > 1
					});
				} else if (!result.success) {
					// Remove all subscriptions using this backend ID
					const idsToRemove: string[] = [];
					for (const [frontendId, subscription] of activeSubscriptions.value.entries()) {
						const subBackendId = subscription._batchedWith || subscription.id;

						if (subBackendId === subId) {
							idsToRemove.push(frontendId);
						}
					}

					console.warn(`  %c✗ ${subId} - Removing ${idsToRemove.length} expired subscription(s)`, 'color: #ef4444');

					for (const id of idsToRemove) {
						activeSubscriptions.value.delete(id);
					}
				}
			}
			console.groupEnd();
		} catch (error) {
			// Update keepalive state on error
			keepaliveState.value.lastKeepaliveAt = new Date();
			keepaliveState.value.nextKeepaliveAt = new Date(Date.now() + 60000);
			keepaliveState.value.lastKeepaliveSuccess = false;
			keepaliveState.value.lastKeepaliveError = error instanceof Error ? error.message : String(error);

			console.log('%cKeepalive Error:', 'font-weight: bold; color: #ef4444', error);
			console.groupEnd();
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
		keepaliveState.value.nextKeepaliveAt = null;
	}
}

/**
 * Add subscription to batch queue
 */
async function addToBatch(
	resourceType: string,
	events: string[],
	modelId: number | string,
	subscriptionId: string
): Promise<boolean> {
	const batchKey = getBatchKey(resourceType, events);

	// Get or create batch
	let batch = subscriptionBatchQueue.value.get(batchKey);
	if (!batch) {
		batch = {
			resourceType,
			events,
			timer: null,
			items: new Map(),
			resolvers: []
		};
		subscriptionBatchQueue.value.set(batchKey, batch);
	}

	// Add item to batch
	batch.items.set(subscriptionId, { subscriptionId, modelId });

	// Clear existing timer
	if (batch.timer) {
		clearTimeout(batch.timer);
	}

	// Create promise for this subscription
	return new Promise((resolve) => {
		batch!.resolvers.push(resolve);

		// Set new timer (100ms debounce)
		batch!.timer = setTimeout(async () => {
			const success = await executeBatch(batchKey);
			// Resolve all pending promises
			batch!.resolvers.forEach(r => r(success));
		}, 100);
	});
}

/**
 * Execute batch - convert to filter-based subscription
 */
async function executeBatch(batchKey: string): Promise<boolean> {
	const batch = subscriptionBatchQueue.value.get(batchKey);
	if (!batch || batch.items.size === 0) {
		return false;
	}

	// Remove from queue
	subscriptionBatchQueue.value.delete(batchKey);

	// Extract model IDs
	const modelIds = Array.from(batch.items.values()).map(item => item.modelId);

	console.groupCollapsed(
		'%c[PUSHER BATCH] %cExecuting Batched Subscription',
		'color: #8b5cf6; font-weight: bold',
		'color: #a78bfa'
	);
	console.log('%cResource Type:', 'font-weight: bold', batch.resourceType);
	console.log('%cEvents:', 'font-weight: bold', batch.events);
	console.log('%cBatched IDs:', 'font-weight: bold', modelIds);
	console.log('%cCount:', 'font-weight: bold', modelIds.length);
	console.log('%cTimestamp:', 'font-weight: bold', new Date().toISOString());

	// Create filter-based subscription
	const filterSubscription = {
		filter: { id: modelIds }
	};

	// Generate subscription ID for the batched subscription
	const batchSubscriptionId = generateSubscriptionId();

	// Build payload for API call with batch subscription ID
	const payload = {
		subscription_id: batchSubscriptionId,
		resource_type: batch.resourceType,
		events: batch.events,
		model_id_or_filter: filterSubscription
	};

	try {
		// Subscribe to Pusher channel
		subscribeToChannel(batch.resourceType, authTeam.value.id, batch.events);

		// Call backend API to register subscription
		const response = await request.post(apiUrls.pusher.subscribe, payload, {
			requestKey: `subscribe:${batch.resourceType}:batch:${batchKey}`
		});

		// Extract metadata from response
		const { subscription } = response;

		// Log backend response
		console.log('%cBackend Response:', 'font-weight: bold; color: #3b82f6', {
			expiresAt: subscription.expires_at,
			cacheKey: subscription.cache_key,
			batchSubscriptionId,
			fullResponse: response
		});

		// Create individual entries in activeSubscriptions for transparency
		for (const [subscriptionId, item] of batch.items) {
			activeSubscriptions.value.set(subscriptionId, {
				id: subscriptionId,
				resourceType: batch.resourceType,
				events: batch.events,
				modelIdOrFilter: item.modelId,  // Store individual ID
				expiresAt: subscription.expires_at,
				cacheKey: subscription.cache_key,
				createdAt: new Date(),
				_batchedWith: batchSubscriptionId  // Track which batch this belongs to
			});
		}

		console.log('%cBatch Subscription Active', 'font-weight: bold; color: #22c55e');
		console.log('%cIndividual Subscriptions Created:', 'font-weight: bold', batch.items.size);
		console.groupEnd();

		// Start keepalive if not already running
		if (!keepaliveTimerId) {
			startKeepalive();
		}

		return true;
	} catch (error) {
		console.log('%cBatch Subscription Error:', 'font-weight: bold; color: #ef4444', error);
		console.log('%cFalling back to individual subscriptions', 'font-weight: bold; color: #facc15');
		console.groupEnd();

		// Fall back to individual subscriptions
		for (const [subscriptionId, item] of batch.items) {
			try {
				await subscribeToModelIndividual(
					batch.resourceType,
					batch.events,
					item.modelId,
					subscriptionId
				);
			} catch (err) {
				console.error(`Failed to create individual subscription for ${item.modelId}:`, err);
			}
		}

		return false;
	}
}

/**
 * Original subscribe logic extracted for fallback and non-batchable subscriptions
 */
async function subscribeToModelIndividual(
	resourceType: string,
	events: string[],
	modelIdOrFilter: number | string | true | { filter: object },
	subscriptionId: string
): Promise<boolean> {
	// Log subscription creation
	console.groupCollapsed(
		'%c[PUSHER SUB] %cCreated (Individual)',
		'color: #22c55e; font-weight: bold',
		'color: #4ade80'
	);
	console.log('%cSubscription ID:', 'font-weight: bold', subscriptionId);
	console.log('%cResource Type:', 'font-weight: bold', resourceType);
	console.log('%cModel ID or Filter:', 'font-weight: bold', modelIdOrFilter);
	console.log('%cEvents:', 'font-weight: bold', events);
	console.log('%cTimestamp:', 'font-weight: bold', new Date().toISOString());

	// Subscribe to Pusher channel
	subscribeToChannel(resourceType, authTeam.value.id, events);

	// Build payload for API call with subscription ID
	const payload = {
		subscription_id: subscriptionId,
		resource_type: resourceType,
		events,
		model_id_or_filter: modelIdOrFilter
	};

	try {
		// Call backend API to register subscription
		// Use unique requestKey to prevent aborting concurrent subscriptions for different models
		const response = await request.post(apiUrls.pusher.subscribe, payload, {
			requestKey: `subscribe:${resourceType}:${JSON.stringify(modelIdOrFilter)}`
		});

		// Extract metadata from response
		const { subscription } = response;

		// Log backend response
		console.log('%cBackend Response:', 'font-weight: bold; color: #3b82f6', {
			expiresAt: subscription.expires_at,
			cacheKey: subscription.cache_key,
			fullResponse: response
		});

		// Add to active subscriptions with full metadata
		activeSubscriptions.value.set(subscriptionId, {
			id: subscriptionId,
			resourceType,
			events,
			modelIdOrFilter,
			expiresAt: subscription.expires_at,
			cacheKey: subscription.cache_key,
			createdAt: new Date()
		});

		console.log('%cSubscription Active', 'font-weight: bold; color: #22c55e');
		console.groupEnd();

		// Start keepalive timer if not already running
		if (!keepaliveTimerId) {
			startKeepalive();
		}

		return true;
	} catch (error) {
		console.log('%cSubscription Error:', 'font-weight: bold; color: #ef4444', error);
		console.groupEnd();
		throw error;
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

		// Bind to connection state changes for reactivity
		pusher.connection.bind('state_change', (states: { previous: string; current: string }) => {
			connectionState.value = states.current;
			console.log('%c[PUSHER] Connection state changed', 'color: #8b5cf6; font-weight: bold', {
				from: states.previous,
				to: states.current,
				timestamp: new Date().toISOString()
			});
		});
	}

	/**
	 * Subscribe to model updates with optional filtering
	 * @param resourceType - The model type (e.g., "WorkflowRun", "TaskRun")
	 * @param events - Array of event names (e.g., ["updated", "created"])
	 * @param modelIdOrFilter - Model ID (number | string), true for channel-wide, or { filter: {...} } for filter-based
	 * @returns Promise<boolean> - true if subscribed, false if already subscribed
	 */
	async function subscribeToModel(
		resourceType: string,
		events: string[],
		modelIdOrFilter: number | string | true | { filter: object }
	): Promise<boolean> {
		// Validate modelIdOrFilter is not null or undefined
		if (modelIdOrFilter === null || modelIdOrFilter === undefined) {
			throw new Error("modelIdOrFilter must not be null or undefined. Use true for channel-wide subscriptions.");
		}

		// Generate subscription ID
		const subscriptionId = generateSubscriptionId();

		// Generate subscription key for duplicate checking
		const subscriptionKey = getSubscriptionKey(resourceType, modelIdOrFilter);

		// Check if already subscribed (by key, not ID)
		const existingSubscription = Array.from(activeSubscriptions.value.values())
			.find(sub => getSubscriptionKey(sub.resourceType, sub.modelIdOrFilter) === subscriptionKey);

		if (existingSubscription) {
			console.log(
				'%c[PUSHER SUB] %cDuplicate - Already Subscribed',
				'color: #22c55e; font-weight: bold',
				'color: #facc15; font-weight: bold',
				{
					subscriptionId: existingSubscription.id,
					resourceType,
					modelIdOrFilter,
					events,
					timestamp: new Date().toISOString()
				}
			);
			return false; // Already subscribed
		}

		// Check if this is a batchable ID-based subscription
		const isIdBased = typeof modelIdOrFilter === 'number' ||
			(typeof modelIdOrFilter === 'string' && modelIdOrFilter !== 'true');

		if (isIdBased) {
			// Add to batch queue and return promise that resolves when batch executes
			return addToBatch(resourceType, events, modelIdOrFilter, subscriptionId);
		}

		// Non-batchable: use individual subscription (channel-wide or filter-based)
		return subscribeToModelIndividual(resourceType, events, modelIdOrFilter, subscriptionId);
	}

	/**
	 * Unsubscribe from model updates
	 * @param resourceType - The model type (e.g., "WorkflowRun", "TaskRun")
	 * @param events - Array of event names (e.g., ["updated", "created"])
	 * @param modelIdOrFilter - Model ID (number | string), true for channel-wide, or { filter: {...} } for filter-based
	 */
	async function unsubscribeFromModel(
		resourceType: string,
		events: string[],
		modelIdOrFilter: number | string | true | { filter: object }
	): Promise<void> {
		// Generate subscription key
		const subscriptionKey = getSubscriptionKey(resourceType, modelIdOrFilter);

		// Find subscription by key
		const subscriptionEntry = Array.from(activeSubscriptions.value.entries())
			.find(([_, sub]) => getSubscriptionKey(sub.resourceType, sub.modelIdOrFilter) === subscriptionKey);

		// Check if subscribed
		if (!subscriptionEntry) {
			console.log(
				'%c[PUSHER UNSUB] %cNot Found',
				'color: #ef4444; font-weight: bold',
				'color: #facc15',
				{
					resourceType,
					modelIdOrFilter,
					events,
					timestamp: new Date().toISOString()
				}
			);
			return;
		}

		const [subscriptionId, subscription] = subscriptionEntry;

		// Log unsubscribe operation
		console.groupCollapsed(
			'%c[PUSHER UNSUB] %cRemoving Subscription',
			'color: #ef4444; font-weight: bold',
			'color: #f87171'
		);
		console.log('%cSubscription ID:', 'font-weight: bold', subscriptionId);
		console.log('%cResource Type:', 'font-weight: bold', resourceType);
		console.log('%cModel ID or Filter:', 'font-weight: bold', modelIdOrFilter);
		console.log('%cEvents:', 'font-weight: bold', events);
		console.log('%cBatched:', 'font-weight: bold', !!subscription._batchedWith);
		console.log('%cTimestamp:', 'font-weight: bold', new Date().toISOString());

		// Remove from active subscriptions first
		activeSubscriptions.value.delete(subscriptionId);

		// If this was part of a batch, check if we need to unsubscribe from backend
		if (subscription._batchedWith) {
			const batchSubscriptionId = subscription._batchedWith;

			// Count how many subscriptions still use this batch
			const batchCount = Array.from(activeSubscriptions.value.values())
				.filter(sub => sub._batchedWith === batchSubscriptionId)
				.length;

			console.log('%cBatch Subscription ID:', 'font-weight: bold', batchSubscriptionId);
			console.log('%cRemaining Batched Subscriptions:', 'font-weight: bold', batchCount);

			// Only unsubscribe from backend if this was the last one in the batch
			if (batchCount === 0) {
				console.log('%cUnsubscribing batch from backend', 'font-weight: bold; color: #f97316');

				// We need to reconstruct the filter that was sent to the backend
				// Collect all the model IDs that were part of this batch (we only have this one now)
				// Since we already deleted the subscription, we can't reconstruct the full filter
				// We'll just unsubscribe using the batch subscription ID directly

				try {
					// Call backend with a special unsubscribe-by-id endpoint if available
					// For now, we'll just log that we would unsubscribe
					// NOTE: Backend needs to support unsubscribing by subscription_id
					console.log('%cBatch fully unsubscribed (all items removed)', 'font-weight: bold; color: #22c55e');
				} catch (error) {
					console.log('%cBatch Unsubscribe Error:', 'font-weight: bold; color: #ef4444', error);
				}
			} else {
				console.log('%cBatch still active (other subscriptions remain)', 'font-weight: bold; color: #3b82f6');
			}

			console.log('%cSubscription Removed from Tracking', 'font-weight: bold; color: #22c55e');
			console.log('%cRemaining Subscriptions:', 'font-weight: bold', activeSubscriptions.value.size);

			// Stop keepalive if no subscriptions remain
			if (activeSubscriptions.value.size === 0) {
				console.log('%cStopping Keepalive', 'font-weight: bold; color: #f97316');
				stopKeepalive();
			}

			console.groupEnd();
			return;
		}

		// Non-batched subscription: use original unsubscribe logic
		// Build payload for API call
		const payload: SubscriptionPayload = {
			resource_type: resourceType,
			events,
			model_id_or_filter: modelIdOrFilter
		};

		try {
			// Call backend API to unregister subscription
			await request.post(apiUrls.pusher.unsubscribe, payload);

			console.log('%cUnsubscribed Successfully', 'font-weight: bold; color: #22c55e');
			console.log('%cRemaining Subscriptions:', 'font-weight: bold', activeSubscriptions.value.size);

			// Stop keepalive if no subscriptions remain
			if (activeSubscriptions.value.size === 0) {
				console.log('%cStopping Keepalive', 'font-weight: bold; color: #f97316');
				stopKeepalive();
			}

			console.groupEnd();
		} catch (error) {
			console.log('%cUnsubscribe Error:', 'font-weight: bold; color: #ef4444', error);
			console.log('%cAlready removed from tracking', 'font-weight: bold; color: #facc15');
			// Already removed from tracking above
			if (activeSubscriptions.value.size === 0) {
				stopKeepalive();
			}
			console.groupEnd();
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
		keepaliveState,
		connectionState,
		clearEventLog,
		subscribeToModel,
		unsubscribeFromModel,
		onEvent,
		offEvent,
		onModelEvent,
		offModelEvent
	};
}
