/**
 * Prompt API Endpoints
 *
 * All prompt-related API endpoints for managing prompts
 * and prompt directives.
 */

import { buildApiUrl } from "../config";

export const prompts = {
	/**
	 * Prompt directives endpoint
	 * @endpoint /prompt/directives
	 */
	directives: buildApiUrl("/prompt/directives"),
} as const;
