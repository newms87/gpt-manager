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
	 * Activity logs endpoint
	 * @endpoint /activity-logs
	 */
	activityLogs: buildApiUrl("/activity-logs"),

	/**
	 * Audit requests endpoint
	 * @endpoint /audit-requests
	 */
	auditRequests: buildApiUrl("/audit-requests"),
} as const;
