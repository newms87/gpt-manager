/**
 * Workflow API Endpoints
 *
 * All workflow-related API endpoints for managing workflow definitions,
 * runs, listeners, and builder interactions.
 */

import { buildApiUrl, createUrlBuilder } from "../config";

export const workflows = {
	/**
	 * Workflow definitions endpoint
	 * @endpoint /workflow-definitions
	 */
	definitions: buildApiUrl("/workflow-definitions"),

	/**
	 * Workflow runs endpoint
	 * @endpoint /workflow-runs
	 */
	runs: buildApiUrl("/workflow-runs"),

	/**
	 * Workflow listeners endpoint
	 * @endpoint /workflow-listeners
	 */
	listeners: buildApiUrl("/workflow-listeners"),

	/**
	 * Workflow builder chat endpoint
	 * @endpoint /workflow-builder-chat
	 */
	builderChat: buildApiUrl("/workflow-builder-chat"),

	/**
	 * Workflow workers info endpoint
	 * @endpoint GET /workflow-runs/:id/workers
	 */
	workersInfo: createUrlBuilder<{ id: number }>(
		(params) => `/workflow-runs/${params.id}/workers`
	),

	/**
	 * Workflow nodes endpoint
	 * @endpoint /workflow-nodes
	 */
	nodes: buildApiUrl("/workflow-nodes"),

	/**
	 * Workflow connections endpoint
	 * @endpoint /workflow-connections
	 */
	connections: buildApiUrl("/workflow-connections"),

	/**
	 * Workflow inputs endpoint
	 * @endpoint /workflow-inputs
	 */
	inputs: buildApiUrl("/workflow-inputs"),

	/**
	 * Dispatch workers endpoint
	 * @endpoint POST /workflow-runs/:id/dispatch-workers
	 */
	dispatchWorkers: createUrlBuilder<{ id: number }>(
		(params) => `/workflow-runs/${params.id}/dispatch-workers`
	),

	/**
	 * Active job dispatches endpoint
	 * @endpoint GET /workflow-runs/:id/active-job-dispatches
	 */
	activeJobDispatches: createUrlBuilder<{ id: number }>(
		(params) => `/workflow-runs/${params.id}/active-job-dispatches`
	),
} as const;
