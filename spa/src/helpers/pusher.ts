import { authTeam, authToken } from "@/helpers";
import { Channel, default as Pusher } from "pusher-js";
import { ActionTargetItem, storeObject } from "quasar-ui-danx";

export interface ChannelEventSubscription {
	channel: string;
	event: string;
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
			"AgentThread": ["updated"]
		};
		const channels = [];

		for (const channelName of Object.keys(channelNames)) {
			const events = channelNames[channelName];
			const channel = pusher.subscribe(`private-${channelName}.${authTeam.value.id}`);

			for (const event of events) {
				channel.bind(event, function (data) {
					console.log("received " + event + " " + channelName, data);
					storeObject(data);
					fireSubscriberEvents(channelName, event, data);
				});
			}
			channels.push(channel);
		}
	}

	function subscribe(channel: string, event: string, callback: (data: ActionTargetItem) => void) {
		subscriptions.push({
			channel,
			event,
			callback
		});
	}

	function fireSubscriberEvents(channel: string, event: string, data: ActionTargetItem) {
		for (const subscription of subscriptions) {
			if (subscription.channel === channel && subscription.event === event) {
				subscription.callback(data);
			}
		}
	}

	return {
		pusher,
		channels,
		subscribe
	};
}
