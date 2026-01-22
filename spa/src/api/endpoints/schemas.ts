/**
 * Schema API Endpoints
 *
 * All schema-related API endpoints for managing database schemas,
 * associations, and history.
 */

import { buildApiUrl } from "../config";

export const schemas = {
	/**
	 * Schemas endpoint
	 * @endpoint /schemas
	 */
	base: buildApiUrl("/schemas"),

	/**
	 * Schema associations endpoint
	 * @endpoint /schema-associations
	 */
	associations: buildApiUrl("/schema-associations"),

	/**
	 * Schema history endpoint
	 * @endpoint /schema-history
	 */
	history: buildApiUrl("/schema-history"),

	/**
	 * Schema definitions endpoint
	 * @endpoint /schemas/definitions
	 */
	definitions: buildApiUrl("/schemas/definitions"),

	/**
	 * Schema fragments endpoint
	 * @endpoint /schemas/fragments
	 */
	fragments: buildApiUrl("/schemas/fragments"),

	/**
	 * Schema associations endpoint (alternative path)
	 * @endpoint /schemas/associations
	 */
	schemasAssociations: buildApiUrl("/schemas/associations"),

	/**
	 * Artifact category definitions endpoint
	 * @endpoint /schemas/artifact-category-definitions
	 */
	artifactCategoryDefinitions: buildApiUrl("/schemas/artifact-category-definitions"),
} as const;
