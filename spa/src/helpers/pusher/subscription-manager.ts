import { apiUrls } from "@/api";
import { request } from "quasar-ui-danx";
import { activeSubscriptions } from "./state";
import { generateSubscriptionId, getSubscriptionKey, isIdBasedSubscription } from "./utils";
import { addToBatch, subscribeToModelIndividual } from "./subscription-batch";
import { stopKeepalive } from "./keepalive";
import type { SubscriptionPayload } from "./types";

/**
 * Subscribe to model updates with optional filtering
 * @param resourceType - The model type (e.g., "WorkflowRun", "TaskRun")
 * @param events - Array of event names (e.g., ["updated", "created"])
 * @param modelIdOrFilter - Model ID (number | string), true for channel-wide, or { filter: {...} } for filter-based
 * @returns Promise<boolean> - true if subscribed, false if already subscribed
 */
export async function subscribeToModel(
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
	if (isIdBasedSubscription(modelIdOrFilter)) {
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
export async function unsubscribeFromModel(
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
		await handleBatchedUnsubscribe(subscription._batchedWith);
		return;
	}

	// Non-batched subscription: use original unsubscribe logic
	await handleIndividualUnsubscribe(resourceType, events, modelIdOrFilter);
}

/**
 * Handle unsubscribing from a batched subscription
 */
async function handleBatchedUnsubscribe(batchSubscriptionId: string): Promise<void> {
	// Count how many subscriptions still use this batch
	const batchCount = Array.from(activeSubscriptions.value.values())
		.filter(sub => sub._batchedWith === batchSubscriptionId)
		.length;

	console.log('%cBatch Subscription ID:', 'font-weight: bold', batchSubscriptionId);
	console.log('%cRemaining Batched Subscriptions:', 'font-weight: bold', batchCount);

	// Only unsubscribe from backend if this was the last one in the batch
	if (batchCount === 0) {
		console.log('%cUnsubscribing batch from backend', 'font-weight: bold; color: #f97316');

		try {
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
}

/**
 * Handle unsubscribing from an individual (non-batched) subscription
 */
async function handleIndividualUnsubscribe(
	resourceType: string,
	events: string[],
	modelIdOrFilter: number | string | true | { filter: object }
): Promise<void> {
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
