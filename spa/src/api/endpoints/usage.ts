/**
 * Usage API Endpoints
 *
 * All usage-related API endpoints for tracking and monitoring
 * API usage and statistics.
 */

import { buildApiUrl } from "../config";

export const usage = {
	/**
	 * Usage events endpoint
	 * @endpoint /usage-events
	 */
	events: buildApiUrl("/usage-events"),

	/**
	 * Usage summaries endpoint
	 * @endpoint /usage-summaries
	 */
	summaries: buildApiUrl("/usage-summaries"),
} as const;
