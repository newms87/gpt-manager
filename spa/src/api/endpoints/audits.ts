/**
 * Audit API Endpoints
 *
 * All audit-related API endpoints for tracking system
 * events and job dispatches.
 */

import { buildApiUrl } from "../config";

export const audits = {
	/**
	 * Job dispatches endpoint
	 * @endpoint /job-dispatches
	 */
	jobDispatches: buildApiUrl("/job-dispatches"),

	/**
	 * Audit requests endpoint
	 * @endpoint /audit-requests
	 */
	auditRequests: buildApiUrl("/audit-requests"),

	/**
	 * API logs endpoint
	 * @endpoint /api-logs
	 */
	apiLogs: buildApiUrl("/api-logs"),
} as const;
