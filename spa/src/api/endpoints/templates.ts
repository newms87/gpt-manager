/**
 * Template API Endpoints
 *
 * All template-related API endpoints for managing template definitions,
 * variables, and history.
 */

import { buildApiUrl } from "../config";

export const templates = {
	/**
	 * Base templates endpoint - supports CRUD operations
	 * @endpoint /template-definitions
	 * @supports GET (list), POST (create), PATCH (update), DELETE (delete)
	 */
	base: buildApiUrl("/template-definitions"),

	/**
	 * Template variables endpoint
	 * @endpoint /template-variables
	 */
	variables: buildApiUrl("/template-variables"),

	/**
	 * Template history endpoint
	 * @endpoint /template-definition-history
	 */
	history: buildApiUrl("/template-definition-history"),
} as const;
