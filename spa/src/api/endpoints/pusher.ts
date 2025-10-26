/**
 * Pusher API Endpoints
 *
 * All Pusher-related API endpoints for managing WebSocket
 * subscriptions and real-time updates.
 */

import { buildApiUrl } from "../config";

export const pusher = {
	/**
	 * Pusher subscribe endpoint
	 * @endpoint POST /pusher/subscribe
	 */
	subscribe: buildApiUrl("/pusher/subscribe"),

	/**
	 * Pusher unsubscribe endpoint
	 * @endpoint POST /pusher/unsubscribe
	 */
	unsubscribe: buildApiUrl("/pusher/unsubscribe"),

	/**
	 * Pusher keepalive endpoint
	 * @endpoint POST /pusher/keepalive
	 */
	keepalive: buildApiUrl("/pusher/keepalive"),
} as const;
