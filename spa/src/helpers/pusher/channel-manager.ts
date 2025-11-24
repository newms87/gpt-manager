import { apiUrls } from "@/api";
import { authTeam, authToken } from "@/helpers";
import { Channel, default as Pusher } from "pusher-js";
import { ActionTargetItem, storeObject } from "quasar-ui-danx";
import { channels, connectionState, getPusher, listeners, modelEventListeners, setPusher } from "./state";
import { recordEvent } from "./event-logger";
import { PUSHER_CLUSTER } from "./constants";
import type { ChannelEventListener } from "./types";

/**
 * Initialize Pusher connection if not already initialized
 */
export function initializePusher() {
	if (getPusher()) {
		return;
	}

	if (!authToken.value) {
		return;
	}

	if (!authTeam.value) {
		return;
	}

	// Initialize Pusher with the auth token and configured to use the auth endpoint
	const pusherInstance = new Pusher(import.meta.env.VITE_PUSHER_API_KEY, {
		cluster: PUSHER_CLUSTER,
		authEndpoint: apiUrls.auth.broadcastingAuth,
		auth: {
			headers: {
				Authorization: `Bearer ${authToken.value}`
			}
		}
	});

	setPusher(pusherInstance);

	// Bind to connection state changes for reactivity
	pusherInstance.connection.bind('state_change', (states: { previous: string; current: string }) => {
		connectionState.value = states.current;
		console.log('%c[PUSHER] Connection state changed', 'color: #8b5cf6; font-weight: bold', {
			from: states.previous,
			to: states.current,
			timestamp: new Date().toISOString()
		});
	});
}

/**
 * Subscribe to a Pusher channel
 */
export function subscribeToChannel(channelName: string, id: string | number, events: string[]): boolean {
	const pusher = getPusher();
	if (!pusher) {
		throw new Error("Pusher not initialized");
	}

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

/**
 * Fire subscriber events for a channel event
 */
export function fireSubscriberEvents(channel: string, event: string, data: ActionTargetItem) {
	// Record event for debug panel
	recordEvent(channel, event, data);

	for (const subscription of listeners) {
		if ([channel, "private-" + channel].includes(subscription.channel) && subscription.events.includes(event)) {
			subscription.callback(data);
		}
	}
}

/**
 * Register an event listener on a channel
 */
export function onEvent(channel: string, event: string | string[], callback: (data: ActionTargetItem) => void) {
	listeners.push({
		channel,
		events: Array.isArray(event) ? event : [event],
		callback
	});
}

/**
 * Register a model-specific event listener
 */
export function onModelEvent(model: ActionTargetItem, event: string | string[], callback: (data: ActionTargetItem) => void) {
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

/**
 * Remove event listener from a channel
 */
export function offEvent(channel: string, event: string | string[], callback: (data: ActionTargetItem) => void) {
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

/**
 * Remove model-specific event listener
 */
export function offModelEvent(model: ActionTargetItem, event: string | string[], callback: (data: ActionTargetItem) => void) {
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
