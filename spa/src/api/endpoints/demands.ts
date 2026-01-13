/**
 * Demand API Endpoints
 *
 * All demand-related API endpoints for managing UI demands.
 */

import { buildApiUrl } from "../config";

export const demands = {
	/**
	 * UI demands endpoint
	 * @endpoint /ui-demands
	 */
	uiDemands: buildApiUrl("/ui-demands"),
} as const;
