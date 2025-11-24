import { apiUrls } from "@/api";
import { request } from "quasar-ui-danx";
import { activeSubscriptions, keepaliveState, getKeepaliveTimerId, setKeepaliveTimerId } from "./state";
import { KEEPALIVE_INTERVAL_MS } from "./constants";

/**
 * Collect unique backend subscription IDs
 */
function collectBackendSubscriptionIds(): {
	subscriptionIds: string[];
	batchedCount: number;
	individualCount: number;
} {
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

	return {
		subscriptionIds: Array.from(backendSubscriptionIds),
		batchedCount,
		individualCount
	};
}

/**
 * Log keepalive operation to console
 */
function logKeepaliveStart(subscriptionIds: string[], batchedCount: number, individualCount: number) {
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
}

/**
 * Update subscription expiration timestamps from keepalive response
 */
function updateSubscriptionExpirations(results: Record<string, any>) {
	const successCount = Object.values(results).filter(r => r.success).length;
	const failureCount = Object.values(results).filter(r => !r.success).length;

	console.log('%cSuccess:', 'font-weight: bold; color: #22c55e', successCount);
	console.log('%cFailures:', 'font-weight: bold; color: #ef4444', failureCount);

	for (const [subId, result] of Object.entries(results)) {
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
}

/**
 * Perform keepalive operation
 */
async function performKeepalive() {
	if (activeSubscriptions.value.size === 0) {
		stopKeepalive();
		return;
	}

	// Collect unique backend subscription IDs (deduplicate batched subscriptions)
	const { subscriptionIds, batchedCount, individualCount } = collectBackendSubscriptionIds();

	logKeepaliveStart(subscriptionIds, batchedCount, individualCount);

	try {
		const response = await request.post(apiUrls.pusher.keepaliveByIds, {
			subscription_ids: subscriptionIds
		});

		// Update keepalive state on success
		keepaliveState.value.lastKeepaliveAt = new Date();
		keepaliveState.value.nextKeepaliveAt = new Date(Date.now() + KEEPALIVE_INTERVAL_MS);
		keepaliveState.value.keepaliveCount++;
		keepaliveState.value.lastKeepaliveSuccess = true;
		keepaliveState.value.lastKeepaliveError = null;

		// Log backend response
		console.log('%cBackend Response:', 'font-weight: bold; color: #22c55e', response);

		// Update expiration timestamps from response
		const results = response.subscriptions;
		updateSubscriptionExpirations(results);

		console.groupEnd();
	} catch (error) {
		// Update keepalive state on error
		keepaliveState.value.lastKeepaliveAt = new Date();
		keepaliveState.value.nextKeepaliveAt = new Date(Date.now() + KEEPALIVE_INTERVAL_MS);
		keepaliveState.value.lastKeepaliveSuccess = false;
		keepaliveState.value.lastKeepaliveError = error instanceof Error ? error.message : String(error);

		console.log('%cKeepalive Error:', 'font-weight: bold; color: #ef4444', error);
		console.groupEnd();
		// Let subscriptions expire naturally - don't retry
	}
}

/**
 * Start keepalive timer to refresh subscriptions
 */
export function startKeepalive() {
	if (getKeepaliveTimerId()) return;

	// Set initial next keepalive time
	keepaliveState.value.nextKeepaliveAt = new Date(Date.now() + KEEPALIVE_INTERVAL_MS);

	const timerId = setInterval(performKeepalive, KEEPALIVE_INTERVAL_MS);
	setKeepaliveTimerId(timerId);
}

/**
 * Stop keepalive timer
 */
export function stopKeepalive() {
	const timerId = getKeepaliveTimerId();
	if (timerId) {
		clearInterval(timerId);
		setKeepaliveTimerId(null);
		keepaliveState.value.nextKeepaliveAt = null;
	}
}
