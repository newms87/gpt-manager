import { authTeam, authToken } from "@/helpers";
import { Channel, default as Pusher } from "pusher-js";

let pusher: Pusher, channels: Channel[];

export function usePusher() {
	if (!pusher) {
		if (!authToken.value) {
			console.log("No auth token, not connecting to Pusher");
			return;
		}

		if (!authTeam.value) {
			console.log("No auth team, not connecting to Pusher");
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

		const channelNames = [
			"WorkflowRun"
		];
		const channels = [];

		for (const channelName of channelNames) {
			const channel = pusher.subscribe(`private-${channelName}.${authTeam.value.id}`);
			channel.bind("updated", function (data) {
				console.log("got message", data);
			});
			channels.push(channel);
		}
	}

	return {
		pusher,
		channels
	};
}
