import { PusherEvent } from "@/types/pusher-debug";
import { activeSubscriptions, eventLog, eventCounts, subscriptionEventCounts } from "./state";
import { MAX_EVENT_LOG_SIZE } from "./constants";

/**
 * Record an event in the debug log
 */
export function recordEvent(resourceType: string, eventName: string, payload: any) {
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

	// Add to event log with matching subscriptions (limit to MAX_EVENT_LOG_SIZE events, FIFO)
	const event: PusherEvent = {
		timestamp: new Date(),
		resourceType,
		eventName,
		modelId,
		payload,
		matchingSubscriptions
	};

	eventLog.value.push(event);
	if (eventLog.value.length > MAX_EVENT_LOG_SIZE) {
		eventLog.value.shift(); // Remove oldest event
	}

	// Increment count for this event type
	const countKey = `${resourceType}:${eventName}`;
	const currentCount = eventCounts.value.get(countKey) || 0;
	eventCounts.value.set(countKey, currentCount + 1);

	// Log event reception with matching subscriptions
	logEventToConsole(resourceType, eventName, modelId, matchingSubscriptions);
}

/**
 * Log event to console with formatting
 */
function logEventToConsole(
	resourceType: string,
	eventName: string,
	modelId: any,
	matchingSubscriptions: string[]
) {
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
export function clearEventLog() {
	eventLog.value = [];
}
