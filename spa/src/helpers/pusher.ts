import { authTeam, authToken } from "@/helpers";
import { Channel, default as Pusher } from "pusher-js";
import { ActionTargetItem, storeObject } from "quasar-ui-danx";

export interface ChannelEventSubscription {
	channel: string;
	events: string[];
	callback: (data: ActionTargetItem) => void;
}

let pusher: Pusher, channels: Channel[];
const subscriptions: ChannelEventSubscription[] = [];

export function usePusher() {
	if (!pusher) {
		if (!authToken.value) {
			console.debug("No auth token, not connecting to Pusher");
			return;
		}

		if (!authTeam.value) {
			console.debug("No auth team, not connecting to Pusher");
			return;
		}

		pusher = new Pusher("22d7fbae1a703b7acaff", {
			cluster: "us2",
			authEndpoint: import.meta.env.VITE_API_URL + "/broadcasting/auth",
			auth: {
				headers: {
					Authorization: `Bearer ${authToken.value}`
				}
			}
		});

		const channelNames = {
			"WorkflowRun": ["updated"],
			"TaskRun": ["updated", "created"],
			"AgentThreadRun": ["updated"]
		};
		const channels = [];

		for (const channelName of Object.keys(channelNames)) {
			const events = channelNames[channelName];
			const channel = pusher.subscribe(`private-${channelName}.${authTeam.value.id}`);

			for (const event of events) {
				channel.bind(event, function (data) {
					storeObject(data);
					fireSubscriberEvents(channelName, event, data);
				});
			}
			channels.push(channel);
		}
	}

	function subscribe(channel: string, event: string | string[], callback: (data: ActionTargetItem) => void) {
		subscriptions.push({
			channel,
			events: Array.isArray(event) ? event : [event],
			callback
		});
	}

	function subscribeToModel(model: ActionTargetItem, event: string | string[], callback: (data: ActionTargetItem) => void) {
		if (!model.id) {
			console.warn("Cannot subscribe to model without id", model);
			return;
		}

		const channel = model.__type.replace("Resource", "");
		subscribe(channel, event, (data: ActionTargetItem) => {
			if (data.id === model.id) {
				callback(data);
			}
		});
	}

	function fireSubscriberEvents(channel: string, event: string, data: ActionTargetItem) {
		for (const subscription of subscriptions) {
			if ([channel, "private-" + channel].includes(subscription.channel) && subscription.events.includes(event)) {
				subscription.callback(data);
			}
		}
	}

	return {
		pusher,
		channels,
		subscribe,
		subscribeToModel
	};
}
