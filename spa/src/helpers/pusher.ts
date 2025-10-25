import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { authTeam, authToken, authUser } from "@/helpers";
import { TaskRun, WorkflowRun } from "@/types";
import { Channel, default as Pusher } from "pusher-js";
import { ActionTargetItem, AnyObject, storeObject } from "quasar-ui-danx";

export interface ChannelEventListener {
	channel: string;
	events: string[];
	callback: (data: ActionTargetItem) => void;
}

export interface UserSubscription {
	endpoint: (params: AnyObject) => Promise<void>;
	params: AnyObject;
}

let pusher: Pusher;
const channels: Channel[] = [];
const listeners: ChannelEventListener[] = [];
const userSubscriptionsMap: Map<string, UserSubscription> = new Map();
// Track model event listeners separately for proper cleanup
const modelEventListeners: Map<string, ChannelEventListener> = new Map();

const defaultChannelNames = {
	"WorkflowRun": ["updated"],
	"TaskRun": ["updated", "created"],
	"AgentThreadRun": ["updated"],
	"AgentThread": ["updated"],
	"StoredFile": ["updated"],
	"JobDispatch": ["updated", "created"],
	"ClaudeCodeGeneration": ["started", "progress", "code_chunk", "completed", "error"],
	"UsageSummary": ["updated"],
	"TeamObject": ["updated"]
};

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
	for (const subscription of listeners) {
		if ([channel, "private-" + channel].includes(subscription.channel) && subscription.events.includes(event)) {
			subscription.callback(data);
		}
	}
}

/**
 *  Adds a user subscription to the list of subscriptions. A user subscription will notify the server every minute that it is listening while the subscription is active.
 *  This way only selective messages are sent to the client, instead of all messages for a channel.
 */
async function addUserSubscription(name: string, userSubscription: UserSubscription) {
	userSubscriptionsMap.set(name, userSubscription);

	// Make sure we initialize the fireUserSubscriptions function
	if (!cancelFireUserSubscriptionsId) {
		await continuouslyFireUserSubscriptions();
	} else {
		await fireUserSubscription(userSubscription);
	}
}

/**
 *  Removes a user subscription from the list of subscriptions.
 *  This will stop notifying the server that the client is listening to a specific event.
 */
function removeUserSubscription(name: string) {
	if (userSubscriptionsMap.has(name)) {
		userSubscriptionsMap.delete(name);
	}
}

/**
 * Fires the user subscription endpoint with the params. This is used to notify the server that the client is listening to a specific event.
 */
async function fireUserSubscription(userSubscription: UserSubscription) {
	await userSubscription.endpoint(userSubscription.params);
}

/**
 *  Fires all user subscriptions. This is used to notify the server that the client is listening to a specific event.
 *  This is done every minute to keep the subscription alive on the server
 */
let cancelFireUserSubscriptionsId: NodeJS.Timeout | null = null;
async function continuouslyFireUserSubscriptions() {
	const promises = [];
	for (const userSubscription of userSubscriptionsMap.values()) {
		promises.push(fireUserSubscription(userSubscription));
	}
	try {
		await Promise.all(promises);
	} finally {
		// Fire the user subscriptions once per minute while there are subscriptions active
		if (userSubscriptionsMap.size > 0) {
			cancelFireUserSubscriptionsId = setTimeout(continuouslyFireUserSubscriptions, 1000 * 60);
		} else {
			cancelFireUserSubscriptionsId = null;
		}
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
			authEndpoint: import.meta.env.VITE_API_URL + "/broadcasting/auth",
			auth: {
				headers: {
					Authorization: `Bearer ${authToken.value}`
				}
			}
		});

		// Subscribe to the default channels (every page session will subscribe to all of these channels)
		for (const channelName of Object.keys(defaultChannelNames)) {
			const events = defaultChannelNames[channelName];
			subscribeToChannel(channelName, authTeam.value.id, events);
		}
	}

	/**
	 *  Subscribes to the processes of a task run.
	 *  This is used to get updates on the processes of a task run.
	 */
	async function subscribeToProcesses(taskRun: TaskRun) {
		subscribeToChannel("TaskProcess", authTeam.value.id, ["updated", "created"]);
		await addUserSubscription("task-processes", { endpoint: dxTaskRun.routes.subscribeToProcesses, params: taskRun });
	}

	function unsubscribeFromProcesses() {
		removeUserSubscription("task-processes");
	}

	async function subscribeToWorkflowJobDispatches(workflowRun: WorkflowRun) {
		subscribeToChannel("JobDispatch", authTeam.value.id, ["updated", "created"]);
		await addUserSubscription("workflow-job-dispatches", {
			endpoint: dxWorkflowRun.routes.subscribeToJobDispatches,
			params: workflowRun
		});
	}

	function unsubscribeFromWorkflowJobDispatches() {
		removeUserSubscription("workflow-job-dispatches");
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
		onEvent,
		offEvent,
		onModelEvent,
		offModelEvent,
		subscribeToProcesses,
		unsubscribeFromProcesses,
		subscribeToWorkflowJobDispatches,
		unsubscribeFromWorkflowJobDispatches
	};
}
