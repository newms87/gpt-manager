/**
 * Centralized API URL Configuration
 *
 * This module provides a single source of truth for all API endpoints
 * used throughout the application. All endpoints are organized by domain
 * and fully typed for type safety.
 *
 * @example
 * ```ts
 * import { apiUrls } from '@/api';
 * import { request } from 'quasar-ui-danx';
 *
 * // Simple usage
 * const response = await request.post(apiUrls.auth.login, credentials);
 *
 * // With ActionRoutes
 * const routes = useActionRoutes(apiUrls.agents.base);
 * ```
 */

// Export base configuration
export { API_BASE_URL, buildApiUrl, createUrlBuilder } from "./config";

// Import all domain endpoints
import { auth } from "./endpoints/auth";
import { agents } from "./endpoints/agents";
import { workflows } from "./endpoints/workflows";
import { tasks } from "./endpoints/tasks";
import { schemas } from "./endpoints/schemas";
import { teams } from "./endpoints/teams";
import { demands } from "./endpoints/demands";
import { oauth } from "./endpoints/oauth";
import { pusher } from "./endpoints/pusher";
import { billing } from "./endpoints/billing";
import { artifacts } from "./endpoints/artifacts";
import { usage } from "./endpoints/usage";
import { fileUpload } from "./endpoints/file-upload";
import { audits } from "./endpoints/audits";
import { contentSources } from "./endpoints/content-sources";
import { prompts } from "./endpoints/prompts";

/**
 * Centralized API URLs organized by domain
 *
 * All API endpoints are grouped by their domain and accessible
 * through this single object for better organization and type safety.
 */
export const apiUrls = {
	auth,
	agents,
	workflows,
	tasks,
	schemas,
	teams,
	demands,
	oauth,
	pusher,
	billing,
	artifacts,
	usage,
	fileUpload,
	audits,
	contentSources,
	prompts,
} as const;
