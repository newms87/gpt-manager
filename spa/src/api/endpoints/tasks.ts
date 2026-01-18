/**
 * Task API Endpoints
 *
 * All task-related API endpoints for managing task definitions,
 * runs, and processes.
 */

import { buildApiUrl, createUrlBuilder } from "../config";

export const tasks = {
	/**
	 * Task definitions endpoint
	 * @endpoint /task-definitions
	 */
	definitions: buildApiUrl("/task-definitions"),

	/**
	 * Task runs endpoint
	 * @endpoint /task-runs
	 */
	runs: buildApiUrl("/task-runs"),

	/**
	 * Task processes endpoint
	 * @endpoint /task-processes
	 */
	processes: buildApiUrl("/task-processes"),

	/**
	 * Task process history endpoint
	 * @endpoint GET /task-processes/:id/history
	 */
	processHistory: createUrlBuilder<{ id: number }>(
		(params) => `/task-processes/${params.id}/history`
	),

	/**
	 * Task run history endpoint
	 * @endpoint GET /task-runs/:id/history
	 */
	runHistory: createUrlBuilder<{ id: number }>(
		(params) => `/task-runs/${params.id}/history`
	),

	/**
	 * Task inputs endpoint
	 * @endpoint /task-inputs
	 */
	inputs: buildApiUrl("/task-inputs"),

	/**
	 * Task artifact filters endpoint
	 * @endpoint /task-artifact-filters
	 */
	artifactFilters: buildApiUrl("/task-artifact-filters"),

	/**
	 * Generate Claude code endpoint
	 * @endpoint POST /task-definitions/:id/generate-claude-code
	 */
	generateClaudeCode: createUrlBuilder<{ id: number }>(
		(params) => `/task-definitions/${params.id}/generate-claude-code`
	),
} as const;
