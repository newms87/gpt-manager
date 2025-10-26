/**
 * Content Source API Endpoints
 *
 * All content source-related API endpoints for managing
 * external content sources and integrations.
 */

import { buildApiUrl } from "../config";

export const contentSources = {
	/**
	 * Content sources endpoint
	 * @endpoint /content-sources
	 */
	base: buildApiUrl("/content-sources"),

	/**
	 * Content source types endpoint
	 * @endpoint /content-source-types
	 */
	types: buildApiUrl("/content-source-types"),
} as const;
