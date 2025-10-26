/**
 * Team API Endpoints
 *
 * All team-related API endpoints for managing teams, objects,
 * and associated resources.
 */

import { buildApiUrl, createUrlBuilder } from "../config";

export const teams = {
	/**
	 * Teams endpoint
	 * @endpoint /teams
	 */
	base: buildApiUrl("/teams"),

	/**
	 * Team objects endpoint
	 * @endpoint /team-objects
	 */
	objects: buildApiUrl("/team-objects"),

	/**
	 * Team object types endpoint
	 * @endpoint /team-object-types
	 */
	objectTypes: buildApiUrl("/team-object-types"),

	/**
	 * Merge team objects endpoint
	 * @endpoint POST /team-objects/:sourceId/merge/:targetId
	 */
	mergeObjects: createUrlBuilder<{ sourceId: number; targetId: number }>(
		(params) => `/team-objects/${params.sourceId}/merge/${params.targetId}`
	),
} as const;
