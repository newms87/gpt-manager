/**
 * Assistant API Endpoints
 *
 * All assistant chat-related API endpoints for managing AI assistant
 * conversations and interactions.
 */

import { buildApiUrl, createUrlBuilder } from "../config";

export const assistant = {
	/**
	 * Assistant chat endpoint
	 * @endpoint POST /assistant/chat
	 */
	chat: buildApiUrl("/assistant/chat"),

	/**
	 * Assistant thread messages endpoint
	 * @endpoint /assistant/thread-messages
	 */
	threadMessages: buildApiUrl("/assistant/thread-messages"),

	/**
	 * Messages endpoint (generic messages)
	 * @endpoint /messages
	 */
	messages: buildApiUrl("/messages"),

	/**
	 * Start new chat endpoint
	 * @endpoint POST /assistant/start-chat
	 */
	startChat: buildApiUrl("/assistant/start-chat"),

	/**
	 * Thread chat endpoint
	 * @endpoint POST /assistant/threads/:threadId/chat
	 */
	threadChat: createUrlBuilder<{ threadId: number }>(
		(params) => `/assistant/threads/${params.threadId}/chat`
	),

	/**
	 * Approve action endpoint
	 * @endpoint POST /assistant/actions/:actionId/approve
	 */
	approveAction: createUrlBuilder<{ actionId: number }>(
		(params) => `/assistant/actions/${params.actionId}/approve`
	),

	/**
	 * Cancel action endpoint
	 * @endpoint POST /assistant/actions/:actionId/cancel
	 */
	cancelAction: createUrlBuilder<{ actionId: number }>(
		(params) => `/assistant/actions/${params.actionId}/cancel`
	),

	/**
	 * Get capabilities for context
	 * @endpoint GET /assistant/capabilities/:context
	 */
	capabilities: createUrlBuilder<{ context: string }>(
		(params) => `/assistant/capabilities/${params.context}`
	),

	/**
	 * Thread details endpoint
	 * @endpoint GET /assistant/threads/:threadId/details
	 */
	threadDetails: createUrlBuilder<{ threadId: number }>(
		(params) => `/assistant/threads/${params.threadId}/details`
	),

	/**
	 * Assistant action endpoint (for updating action status)
	 * @endpoint POST /assistant/actions/:actionId
	 */
	action: createUrlBuilder<{ actionId: number }>(
		(params) => `/assistant/actions/${params.actionId}`
	),
} as const;
