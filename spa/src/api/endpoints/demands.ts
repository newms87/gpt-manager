/**
 * Demand API Endpoints
 *
 * All demand-related API endpoints for managing UI demands,
 * templates, and template variables.
 */

import { buildApiUrl } from "../config";

export const demands = {
	/**
	 * UI demands endpoint
	 * @endpoint /ui-demands
	 */
	uiDemands: buildApiUrl("/ui-demands"),

	/**
	 * Demand templates endpoint
	 * @endpoint /demand-templates
	 */
	templates: buildApiUrl("/demand-templates"),

	/**
	 * Template variables endpoint
	 * @endpoint /template-variables
	 */
	templateVariables: buildApiUrl("/template-variables"),
} as const;
