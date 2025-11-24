import { apiUrls } from "@/api";
import { authTeam } from "@/helpers";
import { request } from "quasar-ui-danx";
import { activeSubscriptions, subscriptionBatchQueue, getKeepaliveTimerId } from "./state";
import { generateSubscriptionId, getBatchKey } from "./utils";
import { subscribeToChannel } from "./channel-manager";
import { startKeepalive } from "./keepalive";
import { SUBSCRIPTION_BATCH_DEBOUNCE_MS } from "./constants";

/**
 * Subscribe to individual model (fallback for batch failures or non-batchable subscriptions)
 */
export async function subscribeToModelIndividual(
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
		if (!getKeepaliveTimerId()) {
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
		if (!getKeepaliveTimerId()) {
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
 * Add subscription to batch queue with debouncing
 */
export async function addToBatch(
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

		// Set new timer (debounce)
		batch!.timer = setTimeout(async () => {
			const success = await executeBatch(batchKey);
			// Resolve all pending promises
			batch!.resolvers.forEach(r => r(success));
		}, SUBSCRIPTION_BATCH_DEBOUNCE_MS);
	});
}
